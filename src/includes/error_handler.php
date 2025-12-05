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
 * Обработчик исключений для PDO
 * 
 * @param PDOException $e
 * @param string $userMessage
 */
function handleDatabaseError(PDOException $e, string $userMessage = 'Ошибка базы данных'): void
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

