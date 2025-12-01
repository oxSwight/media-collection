<?php
// src/includes/csrf.php

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function require_valid_csrf_token(?string $token): void
{
    $expected = csrf_token();
    if (!$token || !hash_equals($expected, $token)) {
        http_response_code(419);
        die('Invalid CSRF token.');
    }
}

