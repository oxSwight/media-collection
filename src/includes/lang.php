<?php
// src/includes/lang.php

// Obsługiwane języki
define('SUPPORTED_LANGUAGES', ['pl', 'en', 'ru']);
define('DEFAULT_LANGUAGE', 'pl');

// Wykrywanie języka
function detect_language(): string
{
    // 1. Sprawdzamy parametr GET PIERWSZY (priorytet dla szybkiego przełączania)
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGUAGES, true)) {
        $newLang = $_GET['lang'];
        // Zapisujemy w sesji od razu
        $_SESSION['lang'] = $newLang;
        
        // Przekierowujemy bez parametru lang dla czystego URL
        $currentUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $queryParams = $_GET;
        unset($queryParams['lang']);
        
        // Zapisujemy wszystkie pozostałe parametry
        if (!empty($queryParams)) {
            $currentUrl .= '?' . http_build_query($queryParams);
        }
        
        // Czyścimy bufor wyjścia przed przekierowaniem
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Szybkie przekierowanie (302 Found) z nagłówkami przeciwko cache
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Location: " . $currentUrl, true, 302);
        exit;
    }
    
    // 2. Sprawdzamy, czy język jest wybrany w sesji
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], SUPPORTED_LANGUAGES, true)) {
        return $_SESSION['lang'];
    }
    
    // 3. Wykrywamy język przeglądarki
    $browserLang = DEFAULT_LANGUAGE;
    
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        
        // Parsujemy nagłówek Accept-Language
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
    
    // Zapisujemy w sesji
    $_SESSION['lang'] = $browserLang;
    return $browserLang;
}

// Ładowanie tłumaczeń z cache
function load_translations(string $lang): array
{
    // Sprawdzamy cache (TTL 24 godziny dla tłumaczeń)
    $cacheKey = "translations_{$lang}";
    $cached = cache_get($cacheKey, 86400);
    
    if ($cached !== null) {
        return $cached;
    }
    
    $file = __DIR__ . "/../lang/{$lang}.php";
    $translations = [];
    
    if (file_exists($file)) {
        $translations = require $file;
    } else {
        // Fallback na polski
        $translations = require __DIR__ . "/../lang/pl.php";
    }
    
    // Zapisujemy w cache
    cache_set($cacheKey, $translations, 86400);
    
    return $translations;
}

// Pobieramy aktualny język
$currentLang = detect_language();
$translations = load_translations($currentLang);

// Funkcja tłumaczenia
function t(string $key, array $params = []): string
{
    global $translations;
    
    $keys = explode('.', $key);
    $value = $translations;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            // Jeśli tłumaczenie nie znalezione, zwracamy klucz
            return $key;
        }
    }
    
    // Zastępujemy parametry
    if (!empty($params)) {
        foreach ($params as $paramKey => $paramValue) {
            $value = str_replace(':' . $paramKey, $paramValue, $value);
        }
    }
    
    return $value;
}

// Krótki alias
function __(string $key, array $params = []): string
{
    return t($key, $params);
}

