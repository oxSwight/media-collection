<?php
// src/includes/lang.php

// Поддерживаемые языки
define('SUPPORTED_LANGUAGES', ['pl', 'en', 'ru']);
define('DEFAULT_LANGUAGE', 'pl');

// Определяем язык
function detect_language(): string
{
    // 1. Проверяем GET параметр ПЕРВЫМ (приоритет для быстрого переключения)
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGUAGES, true)) {
        $newLang = $_GET['lang'];
        // Сохраняем в сессию сразу
        $_SESSION['lang'] = $newLang;
        
        // Перенаправляем без параметра lang для чистого URL
        $currentUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $queryParams = $_GET;
        unset($queryParams['lang']);
        
        // Сохраняем все остальные параметры
        if (!empty($queryParams)) {
            $currentUrl .= '?' . http_build_query($queryParams);
        }
        
        // Очищаем буфер вывода перед редиректом
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Быстрый редирект (302 Found) с заголовками против кэширования
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Location: " . $currentUrl, true, 302);
        exit;
    }
    
    // 2. Проверяем, выбран ли язык в сессии
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], SUPPORTED_LANGUAGES, true)) {
        return $_SESSION['lang'];
    }
    
    // 3. Определяем язык браузера
    $browserLang = DEFAULT_LANGUAGE;
    
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        
        // Парсим Accept-Language заголовок
        preg_match_all('/([a-z]{2})(?:-[A-Z]{2})?(?:;q=([0-9.]+))?/', $acceptLang, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $lang) {
                $lang = strtolower($lang);
                if (in_array($lang, SUPPORTED_LANGUAGES, true)) {
                    $browserLang = $lang;
                    break;
                }
            }
        }
    }
    
    // Сохраняем в сессию
    $_SESSION['lang'] = $browserLang;
    return $browserLang;
}

// Загружаем переводы
function load_translations(string $lang): array
{
    $file = __DIR__ . "/../lang/{$lang}.php";
    if (file_exists($file)) {
        return require $file;
    }
    // Fallback на польский
    return require __DIR__ . "/../lang/pl.php";
}

// Получаем текущий язык
$currentLang = detect_language();
$translations = load_translations($currentLang);

// Функция перевода
function t(string $key, array $params = []): string
{
    global $translations;
    
    $keys = explode('.', $key);
    $value = $translations;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            // Если перевод не найден, возвращаем ключ
            return $key;
        }
    }
    
    // Заменяем параметры
    if (!empty($params)) {
        foreach ($params as $paramKey => $paramValue) {
            $value = str_replace(':' . $paramKey, $paramValue, $value);
        }
    }
    
    return $value;
}

// Короткий алиас
function __(string $key, array $params = []): string
{
    return t($key, $params);
}

