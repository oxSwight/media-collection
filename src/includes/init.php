<?php
// src/includes/init.php

// Включаем буферизацию вывода для возможности редиректа
if (!ob_get_level()) {
    ob_start();
}

// Регистрируем обработчики ошибок СРАЗУ, до подключения других файлов
require_once __DIR__ . '/error_handler.php';
if (function_exists('registerErrorHandlers')) {
    registerErrorHandlers();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/rate_limit.php';
