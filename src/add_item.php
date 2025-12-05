<?php
// src/add_item.php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$type = $_POST['type'] ?? 'movie';

// Предзаполнение из афиши (GET параметры)
$fromAfisha = isset($_GET['from_afisha']);
$prefillTitle = $fromAfisha ? urldecode($_GET['title'] ?? '') : '';
$prefillAuthor = $fromAfisha ? urldecode($_GET['author'] ?? '') : '';
$prefillYear = $fromAfisha ? ($_GET['year'] ?? '') : '';
$prefillImageUrl = $fromAfisha ? urldecode($_GET['image_url'] ?? '') : '';
$prefillReview = $fromAfisha ? urldecode($_GET['review'] ?? '') : '';
$prefillTmdbRating = $fromAfisha ? ($_GET['tmdb_rating'] ?? '') : '';
$prefillTmdbRatingFull = $fromAfisha ? ($_GET['tmdb_rating_full'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);

    // Используем helper функции для валидации
    $title = sanitizeString($_POST['title'] ?? '', 150);
    $type = $_POST['type'] ?? 'movie';
    $author = sanitizeString($_POST['author'] ?? '', 100);
    $year = validateYear($_POST['year'] ?? null);
    $rating = validateRating($_POST['rating'] ?? 5) ?? 5; // По умолчанию 5 если не указано
    $review = sanitizeString($_POST['review'] ?? '', 10000); // Увеличен лимит для рецензий
    
    $allowedTypes = ['movie', 'book'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'movie';
    }
    
    if ($rating === null) {
        $error = t('item.rating_range');
    }
    
    if (empty($title) || empty($author)) {
        $error = t('item.title_required');
    } else {
        $imagePath = null;
        
        // Обработка загруженного файла
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$imagePath, $uploadError] = handle_image_upload($_FILES['image']);
            if ($uploadError) {
                $error = $uploadError;
            }
        }
        // Обработка изображения по URL из афиши
        elseif (isset($_POST['image_url']) && !empty(trim($_POST['image_url']))) {
            $imageUrl = trim($_POST['image_url']);
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                // Пытаемся скачать изображение и сохранить локально
                $ch = curl_init($imageUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_USERAGENT => 'MediaLib/1.0',
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $imageData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                
                if ($imageData !== false && $httpCode === 200 && strlen($imageData) > 100) {
                    // Определяем расширение из Content-Type или URL
                    $ext = 'jpg';
                    if ($contentType) {
                        if (strpos($contentType, 'jpeg') !== false || strpos($contentType, 'jpg') !== false) {
                            $ext = 'jpg';
                        } elseif (strpos($contentType, 'png') !== false) {
                            $ext = 'png';
                        } elseif (strpos($contentType, 'webp') !== false) {
                            $ext = 'webp';
                        } elseif (strpos($contentType, 'gif') !== false) {
                            $ext = 'gif';
                        }
                    } else {
                        $urlPath = parse_url($imageUrl, PHP_URL_PATH);
                        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $urlPath, $matches)) {
                            $ext = strtolower($matches[1]);
                            if ($ext === 'jpeg') $ext = 'jpg';
                        }
                    }
                    
                    $filename = 'img_' . uniqid('', true) . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads/';
                    
                    // Проверяем, существует ли директория
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }
                    
                    $filePath = $uploadDir . $filename;
                    
                    // Сохраняем файл
                    if (file_put_contents($filePath, $imageData) !== false) {
                        // Проверяем, что файл действительно изображение
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->file($filePath);
                        if (strpos($mime, 'image/') === 0) {
                            $imagePath = 'uploads/' . $filename;
                        } else {
                            // Если это не изображение, удаляем файл и пробуем через handle_image_from_url
                            @unlink($filePath);
                            require_once __DIR__ . '/includes/upload.php';
                            $result = handle_image_from_url($imageUrl);
                            $imagePath = $result ?: null;
                        }
                    } else {
                        // Если не удалось сохранить локально, пробуем через handle_image_from_url
                        require_once __DIR__ . '/includes/upload.php';
                        $result = handle_image_from_url($imageUrl);
                        $imagePath = $result ?: null;
                    }
                } else {
                    // Если не удалось скачать, пробуем через handle_image_from_url
                    require_once __DIR__ . '/includes/upload.php';
                    $result = handle_image_from_url($imageUrl);
                    $imagePath = $result ?: null;
                }
            } else {
                $imagePath = null; // Не валидный URL - не сохраняем
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

                $mediaId = (int)$pdo->lastInsertId();

                // Логируем активность
                $act = $pdo->prepare("INSERT INTO activities (user_id, media_id, type) VALUES (?, ?, 'add_item')");
                $act->execute([$_SESSION['user_id'], $mediaId]);
                
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
            <?php if ($fromAfisha): ?>
                <div style="background: #e3f2fd; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
                    <strong>ℹ️ <?= htmlspecialchars(t('item.from_afisha')) ?></strong><br>
                    <small><?= htmlspecialchars(t('item.from_afisha_desc')) ?></small>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label><?= htmlspecialchars(t('item.title')) ?></label>
                <input type="text" name="title" required placeholder="np. Matrix" value="<?= htmlspecialchars($_POST['title'] ?? $prefillTitle) ?>">
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
                    <input type="number" name="year" value="<?= htmlspecialchars($_POST['year'] ?? ($prefillYear ?: date('Y'))) ?>">
                </div>
            </div>

            <div class="form-group">
                <label><?= htmlspecialchars(t('item.author')) ?></label>
                <input type="text" name="author" required placeholder="np. Wachowski" value="<?= htmlspecialchars($_POST['author'] ?? $prefillAuthor) ?>">
            </div>

            <div class="form-group">
                <label>
                    <?= htmlspecialchars(t('item.rating')) ?>
                    <?php if ($fromAfisha && !empty($prefillTmdbRatingFull)): ?>
                        <span style="color: #636e72; font-size: 0.85rem; font-weight: normal;">
                            (TMDb: <?= htmlspecialchars(number_format((float)$prefillTmdbRatingFull, 1)) ?>/10)
                        </span>
                    <?php endif; ?>
                </label>
                <input type="number" name="rating" min="1" max="10" value="<?= htmlspecialchars($_POST['rating'] ?? ($prefillTmdbRating ?: '5')) ?>">
            </div>

            <div class="form-group">
                <label><?= htmlspecialchars(t('item.image')) ?></label>
                <?php if ($fromAfisha && $prefillImageUrl): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="<?= htmlspecialchars($prefillImageUrl) ?>" alt="Poster" style="max-width: 200px; max-height: 300px; border-radius: 5px; border: 2px solid #dfe6e9;">
                        <input type="hidden" name="image_url" value="<?= htmlspecialchars($prefillImageUrl) ?>">
                        <p style="font-size: 0.85em; color: #636e72; margin-top: 5px;"><?= htmlspecialchars(t('item.image_from_afisha')) ?></p>
                    </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*">
                <?php if ($fromAfisha && $prefillImageUrl): ?>
                    <p style="font-size: 0.85em; color: #636e72; margin-top: 5px;"><?= htmlspecialchars(t('item.image_upload_optional')) ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label><?= htmlspecialchars(t('item.review')) ?></label>
                <textarea name="review" rows="4" placeholder="<?= htmlspecialchars(t('item.review_placeholder')) ?>"><?= htmlspecialchars($_POST['review'] ?? $prefillReview) ?></textarea>
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="index.php" style="flex: 1; text-align: center; padding: 10px; border: 2px solid #dfe6e9; border-radius: 30px; text-decoration: none; color: #636e72; font-weight: bold;"><?= htmlspecialchars(t('item.cancel')) ?></a>
                <button type="submit" class="btn-submit" style="flex: 2; margin-top: 0;"><?= htmlspecialchars(t('item.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>