<?php
// src/install_schema.php
// Скрипт для создания всех таблиц в базе данных

// Простая защита - можно добавить проверку на админа или секретный ключ
$secretKey = $_GET['key'] ?? '';
$expectedKey = getenv('INSTALL_KEY') ?: 'install_media_collection_2024';

if ($secretKey !== $expectedKey) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Installation</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .error { background: #ffecec; color: #c0392b; padding: 15px; border-radius: 8px; }
            .info { background: #e3f2fd; color: #1976d2; padding: 15px; border-radius: 8px; margin-top: 20px; }
            code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>⚠️ Доступ запрещен</h2>
            <p>Для выполнения установки схемы БД добавь параметр <code>?key=install_media_collection_2024</code> к URL.</p>
            <p>Или установи переменную окружения <code>INSTALL_KEY</code> и используй её значение.</p>
        </div>
        <div class="info">
            <strong>Пример:</strong><br>
            <code>https://your-site.com/install_schema.php?key=install_media_collection_2024</code>
        </div>
    </body>
    </html>
    ');
}

// Подключаемся к базе данных
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

if (!$host || !$db || !$user || !$pass) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Installation - Error</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .error { background: #ffecec; color: #c0392b; padding: 15px; border-radius: 8px; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>❌ Ошибка подключения</h2>
            <p>Не установлены переменные окружения для подключения к базе данных:</p>
            <ul>
                <li>DB_HOST: ' . ($host ? '✓' : '✗') . '</li>
                <li>DB_NAME: ' . ($db ? '✓' : '✗') . '</li>
                <li>DB_USER: ' . ($user ? '✓' : '✗') . '</li>
                <li>DB_PASS: ' . ($pass ? '✓' : '✗') . '</li>
            </ul>
        </div>
    </body>
    </html>
    ');
}

$dsn = "pgsql:host=$host;port=5432;dbname=$db;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Installation - Connection Error</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .error { background: #ffecec; color: #c0392b; padding: 15px; border-radius: 8px; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>❌ Ошибка подключения к базе данных</h2>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
        </div>
    </body>
    </html>
    ');
}

// Читаем SQL-файл
$schemaFile = __DIR__ . '/../database/schema.sql';
if (!file_exists($schemaFile)) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Installation - Error</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .error { background: #ffecec; color: #c0392b; padding: 15px; border-radius: 8px; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>❌ Файл не найден</h2>
            <p>Файл схемы не найден: <code>' . htmlspecialchars($schemaFile) . '</code></p>
        </div>
    </body>
    </html>
    ');
}

$sqlContent = file_get_contents($schemaFile);

// Удаляем комментарии (однострочные и многострочные)
$sqlContent = preg_replace('/--.*$/m', '', $sqlContent); // Однострочные комментарии
$sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent); // Многострочные комментарии

// Разбиваем на отдельные команды по точке с запятой
$statements = array_filter(
    array_map('trim', explode(';', $sqlContent)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^\s*COMMENT\s+ON/i', $stmt);
    }
);

$results = [];
$successCount = 0;
$errorCount = 0;

// Выполняем каждую команду
foreach ($statements as $index => $statement) {
    if (empty(trim($statement))) {
        continue;
    }
    
    try {
        $pdo->exec($statement);
        $results[] = [
            'success' => true,
            'statement' => substr($statement, 0, 100) . (strlen($statement) > 100 ? '...' : ''),
            'message' => '✓ Выполнено успешно'
        ];
        $successCount++;
    } catch (PDOException $e) {
        $results[] = [
            'success' => false,
            'statement' => substr($statement, 0, 100) . (strlen($statement) > 100 ? '...' : ''),
            'message' => '✗ Ошибка: ' . htmlspecialchars($e->getMessage())
        ];
        $errorCount++;
    }
}

// Выводим результаты
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Installation - Results</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .summary-card {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .summary-card.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .summary-card.total {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }
        .summary-card p {
            margin: 0;
            font-weight: bold;
        }
        .results {
            margin-top: 30px;
        }
        .result-item {
            padding: 12px;
            margin: 8px 0;
            border-radius: 5px;
            border-left: 4px solid;
            background: #f8f9fa;
        }
        .result-item.success {
            border-color: #28a745;
            background: #d4edda;
        }
        .result-item.error {
            border-color: #dc3545;
            background: #f8d7da;
        }
        .result-item code {
            display: block;
            margin: 5px 0;
            font-size: 0.9em;
            color: #495057;
            background: rgba(0,0,0,0.05);
            padding: 5px;
            border-radius: 3px;
        }
        .message {
            font-weight: bold;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            font-size: 1.2em;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Установка схемы базы данных</h1>
        
        <div class="summary">
            <div class="summary-card total">
                <h3><?= count($results) ?></h3>
                <p>Всего команд</p>
            </div>
            <div class="summary-card success">
                <h3><?= $successCount ?></h3>
                <p>Успешно</p>
            </div>
            <div class="summary-card error">
                <h3><?= $errorCount ?></h3>
                <p>Ошибок</p>
            </div>
        </div>

        <?php if ($errorCount === 0): ?>
            <div class="success-message">
                ✅ <strong>Все таблицы успешно созданы!</strong><br>
                Теперь можно использовать приложение.
            </div>
        <?php elseif ($successCount > 0): ?>
            <div class="warning">
                ⚠️ Некоторые команды выполнились с ошибками. Проверь детали ниже.
            </div>
        <?php else: ?>
            <div class="warning">
                ❌ Все команды выполнились с ошибками. Проверь подключение к базе данных.
            </div>
        <?php endif; ?>

        <div class="results">
            <h2>Детали выполнения:</h2>
            <?php foreach ($results as $result): ?>
                <div class="result-item <?= $result['success'] ? 'success' : 'error' ?>">
                    <div class="message"><?= $result['message'] ?></div>
                    <code><?= htmlspecialchars($result['statement']) ?></code>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($errorCount === 0): ?>
            <div style="margin-top: 30px; padding: 20px; background: #e3f2fd; border-radius: 8px; text-align: center;">
                <p><strong>🎉 Установка завершена!</strong></p>
                <p style="margin-top: 10px;">
                    <a href="index.php" style="color: #1976d2; text-decoration: none; font-weight: bold;">→ Перейти на главную страницу</a>
                </p>
                <p style="margin-top: 15px; font-size: 0.9em; color: #666;">
                    <strong>Важно:</strong> После успешной установки удали или переименуй этот файл (<code>install_schema.php</code>) для безопасности.
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
