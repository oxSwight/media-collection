<?php
// src/import.php - Импорт данных из внешних сервисов (Letterboxd, Goodreads)

require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$error = '';
$success = '';

// Обработка импорта
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);
    
    $service = $_POST['service'] ?? '';
    $data = $_POST['data'] ?? '';
    
    if (empty($data)) {
        $error = 'Пожалуйста, введите данные для импорта';
    } else {
        try {
            $imported = 0;
            $lines = explode("\n", trim($data));
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Парсим CSV формат: Title,Year,Rating,Review
                $parts = str_getcsv($line);
                if (count($parts) < 2) continue;
                
                $title = trim($parts[0]);
                $year = !empty($parts[1]) ? (int)$parts[1] : null;
                $rating = !empty($parts[2]) ? (int)$parts[2] : 5;
                $review = !empty($parts[3]) ? trim($parts[3]) : '';
                $type = $service === 'goodreads' ? 'book' : 'movie';
                
                if (empty($title)) continue;
                
                // Проверяем, нет ли уже такого элемента
                $check = $pdo->prepare("SELECT id FROM media_items WHERE user_id = ? AND LOWER(title) = LOWER(?) AND type = ?");
                $check->execute([$userId, $title, $type]);
                if ($check->fetchColumn()) continue;
                
                // Добавляем элемент
                $stmt = $pdo->prepare("
                    INSERT INTO media_items (user_id, title, type, author_director, release_year, rating, review)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $title,
                    $type,
                    '', // Автор будет пустым при импорте
                    $year,
                    max(1, min(10, $rating)),
                    $review
                ]);
                
                $imported++;
            }
            
            if ($imported > 0) {
                $success = "Успешно импортировано: $imported элементов";
            } else {
                $error = 'Не удалось импортировать данные. Проверьте формат.';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка при импорте: ' . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="dashboard">
    <div class="header-actions">
        <h2><?= htmlspecialchars(t('import.title') ?? 'Импорт данных') ?></h2>
    </div>

    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background: #00b894; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            ✅ <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="auth-form" style="max-width: 800px;">
        <h3><?= htmlspecialchars(t('import.instructions') ?? 'Инструкции по импорту') ?></h3>
        
        <div style="background: #e3f2fd; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <h4>Формат данных (CSV):</h4>
            <p><code>Название,Год,Оценка,Рецензия</code></p>
            <p style="font-size: 0.9rem; color: #636e72;">
                Пример:<br>
                <code>Matrix,1999,9,Отличный фильм<br>
                Inception,2010,8,Интересный сюжет</code>
            </p>
        </div>

        <form method="POST" action="import.php">
            <?= csrf_input(); ?>
            
            <div class="form-group">
                <label><?= htmlspecialchars(t('import.service') ?? 'Сервис') ?></label>
                <select name="service" required>
                    <option value="letterboxd">Letterboxd (Фильмы)</option>
                    <option value="goodreads">Goodreads (Книги)</option>
                    <option value="csv">CSV файл (Универсальный)</option>
                </select>
            </div>

            <div class="form-group">
                <label><?= htmlspecialchars(t('import.data') ?? 'Данные для импорта (CSV формат)') ?></label>
                <textarea name="data" rows="15" placeholder="Название,Год,Оценка,Рецензия&#10;Matrix,1999,9,Отличный фильм&#10;Inception,2010,8,Интересный сюжет" required></textarea>
            </div>

            <button type="submit" class="btn-submit">
                <?= htmlspecialchars(t('import.submit') ?? 'Импортировать') ?>
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

