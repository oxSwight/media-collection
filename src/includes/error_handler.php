<?php
// src/includes/error_handler.php - Централизованная обработка ошибок

/**
 * Логирование ошибок
 * 
 * @param string $message
 * @param array $context Дополнительный контекст
 */
function logError(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logMessage = "[$timestamp] $message$contextStr\n";
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Безопасное отображение ошибки пользователю
 * 
 * @param string $userMessage Сообщение для пользователя
 * @param string|null $internalMessage Внутреннее сообщение для логирования
 * @param array $context Дополнительный контекст
 */
function showError(string $userMessage, ?string $internalMessage = null, array $context = []): void
{
    if ($internalMessage) {
        logError($internalMessage, $context);
    }
    
    // В продакшене не показываем детали ошибок
    $isProduction = getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod';
    
    if ($isProduction && $internalMessage) {
        // Показываем общее сообщение
        echo "<div class='error-msg'>" . htmlspecialchars($userMessage) . "</div>";
    } else {
        // В разработке показываем детали
        echo "<div class='error-msg'>" . htmlspecialchars($userMessage) . "</div>";
        if ($internalMessage && !$isProduction) {
            echo "<div class='error-msg' style='font-size:0.8em; color:#999;'>" . htmlspecialchars($internalMessage) . "</div>";
        }
    }
}

/**
 * Обработчик исключений для PDO (точечное использование в коде)
 * 
 * @param PDOException $e
 * @param string $userMessage
 */
function handleDatabaseError(PDOException $e, string $userMessage = 'Wystąpił błąd bazy danych. Spróbuj ponownie później.'): void
{
    logError('Database error: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    showError($userMessage, $e->getMessage());
}

/**
 * JSON ответ с ошибкой
 * 
 * @param string $message
 * @param int $code HTTP код
 * @param array $additionalData
 */
function jsonError(string $message, int $code = 400, array $additionalData = []): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    
    $response = array_merge([
        'success' => false,
        'error' => $message
    ], $additionalData);
    
    echo json_encode($response);
    exit;
}

/**
 * JSON ответ с успехом
 * 
 * @param array $data
 * @param int $code HTTP код
 */
function jsonSuccess(array $data = [], int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    
    $response = array_merge(['success' => true], $data);
    
    echo json_encode($response);
    exit;
}

/**
 * Глобальный обработчик необработанных исключений
 * 
 * @param Throwable $e
 */
function globalExceptionHandler(Throwable $e): void
{
    logError('Uncaught exception: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    // Стараемся не оставлять пользователя на "пустой" странице с трассировкой
    if (!headers_sent()) {
        http_response_code(500);
    }

    $isProduction = getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod';
    $message = 'Coś poszło nie tak. Spróbuj ponownie później.';

    echo "<div class='error-msg' style=\"max-width:600px;margin:40px auto;padding:20px;border-radius:8px;background:#ffecec;color:#c0392b;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;text-align:center;\">";
    echo htmlspecialchars($message);

    if (!$isProduction) {
        echo "<div style='margin-top:10px;font-size:0.85rem;color:#7f8c8d;'>"
            . htmlspecialchars($e->getMessage())
            . "</div>";
    }

    $backUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    $backUrlEsc = htmlspecialchars($backUrl);
    echo "<div style='margin-top:15px;'><a href=\"{$backUrlEsc}\" style=\"color:#2980b9;text-decoration:underline;\">Wróć na poprzednią stronę</a></div>";
    echo "</div>";

    exit;
}

/**
 * Регистрация глобальных обработчиков ошибок / исключений
 */
function registerErrorHandlers(): void
{
    set_exception_handler('globalExceptionHandler');
}
