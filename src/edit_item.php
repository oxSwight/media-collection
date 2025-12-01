<?php
// src/edit_item.php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT * FROM media_items WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$item = $stmt->fetch();

if (!$item) {
    require_once 'includes/header.php';
    echo "<div class='container'><p>" . htmlspecialchars(t('item.not_found')) . "</p></div>";
    require_once 'includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);

    $title = trim($_POST['title']);
    $type = $_POST['type'] ?? $item['type'];
    $author = trim($_POST['author']);
    $year = (int)($_POST['year'] ?? $item['release_year']);
    $rating = (int)($_POST['rating'] ?? $item['rating']);
    $review = trim($_POST['review']);

    $allowedTypes = ['movie', 'book'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = $item['type'];
    }

    $currentYear = (int)date('Y') + 1;
    if ($year < 1900 || $year > $currentYear) {
        $year = $item['release_year'];
    }

    if ($rating < 1 || $rating > 10) {
        $error = t('item.rating_range');
    }

    if (empty($title) || empty($author)) {
        $error = t('item.title_required');
    } else {
        $imagePath = $item['image_path'];

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$newPath, $uploadError] = handle_image_upload($_FILES['image']);
            if ($uploadError) {
                $error = $uploadError;
            } else {
                safe_delete_upload($imagePath);
                $imagePath = $newPath;
            }
        }

        if (!$error) {
            $updateStmt = $pdo->prepare("
                UPDATE media_items 
                SET title=?, type=?, author_director=?, release_year=?, rating=?, review=?, image_path=?
                WHERE id=? AND user_id=?
            ");
            
            if ($updateStmt->execute([$title, $type, $author, $year, $rating, $review, $imagePath, $id, $_SESSION['user_id']])) {
                $success = t('item.updated_success');
                $item['title'] = $title;
                $item['type'] = $type;
                $item['author_director'] = $author;
                $item['release_year'] = $year;
                $item['rating'] = $rating;
                $item['review'] = $review;
                $item['image_path'] = $imagePath;
            } else {
                $error = t('common.error');
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-form" style="max-width: 600px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2><?= htmlspecialchars(t('item.edit')) ?></h2>
        <a href="index.php" style="color: #636e72; text-decoration: none;">&larr; <?= htmlspecialchars(t('item.back')) ?></a>
    </div>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
        <div style="background: #55efc4; color: #00b894; padding: 10px; margin-bottom: 10px; border-radius: 5px;">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form action="edit_item.php?id=<?= $id ?>" method="POST" enctype="multipart/form-data">
        <?= csrf_input(); ?>
        <div class="form-group">
            <label><?= htmlspecialchars(t('item.title')) ?></label>
            <input type="text" name="title" required value="<?= htmlspecialchars($item['title']) ?>">
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label><?= htmlspecialchars(t('item.type')) ?></label>
                <select name="type" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="movie" <?= $item['type'] == 'movie' ? 'selected' : '' ?>><?= htmlspecialchars(t('collection.movies')) ?></option>
                    <option value="book" <?= $item['type'] == 'book' ? 'selected' : '' ?>><?= htmlspecialchars(t('collection.books')) ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars(t('item.year')) ?></label>
                <input type="number" name="year" value="<?= htmlspecialchars($item['release_year']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label><?= htmlspecialchars(t('item.author')) ?></label>
            <input type="text" name="author" required value="<?= htmlspecialchars($item['author_director']) ?>">
        </div>

        <div class="form-group">
            <label><?= htmlspecialchars(t('item.rating')) ?></label>
            <input type="number" name="rating" min="1" max="10" value="<?= htmlspecialchars($item['rating']) ?>">
        </div>

        <div class="form-group">
            <label><?= htmlspecialchars(t('item.image')) ?></label>
            <?php if ($item['image_path']): ?>
                <div style="margin-bottom: 5px;">
                    <img src="<?= htmlspecialchars($item['image_path']) ?>" style="height: 50px; border-radius: 4px;">
                </div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">
        </div>

        <div class="form-group">
            <label><?= htmlspecialchars(t('item.review')) ?></label>
            <textarea name="review" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"><?= htmlspecialchars($item['review']) ?></textarea>
        </div>

        <button type="submit" class="btn-submit"><?= htmlspecialchars(t('item.save_changes')) ?></button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>