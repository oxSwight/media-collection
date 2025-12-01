<?php
// src/includes/init.php

// Включаем буферизацию вывода для возможности редиректа
if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/lang.php';

