<?php
// src/add_item.php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$type = $_POST['type'] ?? 'movie';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);

    $title = trim($_POST['title']);
    $type = $_POST['type'] ?? 'movie';
    $author = trim($_POST['author']);
    $year = (int)($_POST['year'] ?? date('Y'));
    $rating = (int)($_POST['rating'] ?? 5);
    $review = trim($_POST['review']);
    $allowedTypes = ['movie', 'book'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'movie';
    }
    $currentYear = (int)date('Y') + 1;
    if ($year < 1900 || $year > $currentYear) {
        $year = null;
    }
    if ($rating < 1 || $rating > 10) {
        $error = t('item.rating_range');
    }
    
    if (empty($title) || empty($author)) {
        $error = t('item.title_required');
    } else {
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$imagePath, $uploadError] = handle_image_upload($_FILES['image']);
            if ($uploadError) {
                $error = $uploadError;
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("
                INSERT INTO media_items (user_id, title, type, author_director, release_year, rating, image_path, review)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            try {
                $stmt->execute([
                    $_SESSION['user_id'], $title, $type, $author, $year, $rating, $imagePath, $review
                ]);
                
                // 4. ПЕРЕНАПРАВЛЕНИЕ
                // Так как HTML еще не выведен, header() сработает без ошибок.
                header("Location: index.php?msg=added");
                exit;
                
            } catch (PDOException $e) {
                $error = t('common.error') . ": " . $e->getMessage();
            }
        }
    }
}

// 5. ТЕПЕРЬ подключаем HTML шапку (вывод начинается здесь)
require_once 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-form" style="max-width: 600px;">
        <h2><?= htmlspecialchars(t('item.add')) ?></h2>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="add_item.php" method="POST" enctype="multipart/form-data">
            <?= csrf_input(); ?>
            <div class="form-group">
                <label><?= htmlspecialchars(t('item.title')) ?></label>
                <input type="text" name="title" required placeholder="np. Matrix" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label><?= htmlspecialchars(t('item.type')) ?></label>
                    <select name="type">
                        <option value="movie" <?= ($type ?? 'movie') === 'movie' ? 'selected' : '' ?>><?= htmlspecialchars(t('collection.movies')) ?></option>
                        <option value="book" <?= ($type ?? '') === 'book' ? 'selected' : '' ?>><?= htmlspecialchars(t('collection.books')) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(t('item.year')) ?></label>
                    <input type="number" name="year" value="<?= htmlspecialchars($_POST['year'] ?? '2024') ?>">
                </div>
            </div>

            <div class="form-group">
                <label><?= htmlspecialchars(t('item.author')) ?></label>
                <input type="text" name="author" required placeholder="np. Wachowski" value="<?= htmlspecialchars($_POST['author'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label><?= htmlspecialchars(t('item.rating')) ?></label>
                    <input type="number" name="rating" min="1" max="10" value="<?= htmlspecialchars($_POST['rating'] ?? '5') ?>">
            </div>

            <div class="form-group">
                <label><?= htmlspecialchars(t('item.image')) ?></label>
                <input type="file" name="image" accept="image/*">
            </div>

            <div class="form-group">
                <label><?= htmlspecialchars(t('item.review')) ?></label>
                <textarea name="review" rows="4"><?= htmlspecialchars($_POST['review'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="index.php" style="flex: 1; text-align: center; padding: 10px; border: 2px solid #dfe6e9; border-radius: 30px; text-decoration: none; color: #636e72; font-weight: bold;"><?= htmlspecialchars(t('item.cancel')) ?></a>
                <button type="submit" class="btn-submit" style="flex: 2; margin-top: 0;"><?= htmlspecialchars(t('item.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>