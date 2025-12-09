<?php
// src/includes/header.php

require_once __DIR__ . '/init.php';

$avatarUrl = null;
$myId = $_SESSION['user_id'] ?? 0;
$isAdmin = !empty($_SESSION['is_admin']);

// Определяем текущую страницу для активного состояния навигации
$currentPage = basename($_SERVER['PHP_SELF']);

if ($myId) {
    $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
    $stmt->execute([$myId]);
    $avatarUrl = $stmt->fetchColumn();
    
    // Подсчитываем входящие запросы в друзья
    $pendingRequestsStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM friendships 
        WHERE receiver_id = ? AND status = 'pending'
    ");
    $pendingRequestsStmt->execute([$myId]);
    $pendingRequestsCount = (int)$pendingRequestsStmt->fetchColumn();
} else {
    $pendingRequestsCount = 0;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <!-- Ważny tag dla responsywności mobilnej -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <title><?= htmlspecialchars(t('site.title')) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=2.1">
    <script>
        // Ulepszony system zarządzania motywem
        (function() {
            'use strict';
            
            // Funkcja do bezpiecznej pracy z localStorage
            const storage = {
                get: function(key) {
                    try {
                        return localStorage.getItem(key);
                    } catch (e) {
                        console.warn('localStorage niedostępny:', e);
                        return null;
                    }
                },
                set: function(key, value) {
                    try {
                        localStorage.setItem(key, value);
                        return true;
                    } catch (e) {
                        console.warn('Nie udało się zapisać w localStorage:', e);
                        return false;
                    }
                }
            };
            
            // Funkcja zastosowania motywu
            function applyTheme(isDark) {
                const root = document.documentElement;
                if (isDark) {
                    root.classList.add('dark-theme');
                } else {
                    root.classList.remove('dark-theme');
                }
            }
            
            // Funkcja synchronizacji checkbox z motywem
            function syncCheckbox() {
                const checkbox = document.getElementById('theme-toggle');
                if (checkbox) {
                    const isDark = document.documentElement.classList.contains('dark-theme');
                    checkbox.checked = isDark;
                }
            }
            
            // Inicjalizacja motywu przy ładowaniu strony (przed renderowaniem)
            const savedTheme = storage.get('theme');
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            // Priorytet: zapisany motyw > ustawienie systemowe > jasny
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                applyTheme(true);
            } else {
                applyTheme(false);
            }
            
            // Synchronizacja checkbox po załadowaniu DOM
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', syncCheckbox);
            } else {
                syncCheckbox();
            }
            
            // Globalna funkcja przełączania motywu
            window.toggleTheme = function() {
                const root = document.documentElement;
                const isDark = root.classList.contains('dark-theme');
                const newIsDark = !isDark;
                
                applyTheme(newIsDark);
                syncCheckbox();
                storage.set('theme', newIsDark ? 'dark' : 'light');
                
                // Dodatkowa weryfikacja synchronizacji przez małe opóźnienie
                setTimeout(syncCheckbox, 100);
            };
            
            // Nasłuchujemy zmian motywu systemowego (opcjonalnie)
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                    // Zastosowujemy tylko jeśli użytkownik nie zapisał swojego motywu
                    if (!storage.get('theme')) {
                        applyTheme(e.matches);
                        syncCheckbox();
                    }
                });
            }
        })();
        
        window.csrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        
        // Funkcja przełączania menu mobilnego
        function toggleMobileMenu() {
            const navLinks = document.getElementById('navLinks');
            const toggle = document.querySelector('.mobile-menu-toggle');
            if (navLinks) {
                navLinks.classList.toggle('mobile-open');
                if (toggle) {
                    toggle.classList.toggle('active');
                }
            }
        }
        
        // Zamykamy menu przy kliknięciu poza nim
        document.addEventListener('click', function(event) {
            const nav = document.querySelector('.navbar');
            const navLinks = document.getElementById('navLinks');
            const toggle = document.querySelector('.mobile-menu-toggle');
            if (nav && navLinks && toggle && !nav.contains(event.target) && navLinks.classList.contains('mobile-open')) {
                navLinks.classList.remove('mobile-open');
                toggle.classList.remove('active');
            }
        });
    </script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-header">
                <a href="index.php" class="logo <?= $currentPage === 'index.php' ? 'nav-active' : '' ?>">
                    <span style="font-size: 1.8rem;">🍿</span> <?= htmlspecialchars(t('site.title')) ?>
                </a>
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
            <ul class="nav-links" id="navLinks">
                <?php if ($myId): ?>
                    <li>
                        <div class="toggle-switch">
                            <label>
                                <input type="checkbox" id="theme-toggle" onchange="toggleTheme()" aria-label="<?= htmlspecialchars(t('nav.toggle_theme') ?? 'Переключить тему') ?>" aria-pressed="false">
                                <span class="slider" role="switch" aria-label="<?= htmlspecialchars(t('nav.toggle_theme') ?? 'Переключить тему') ?>"></span>
                            </label>
                        </div>
                    </li>
                    <!-- Wpis aktywności -->
                    <li><a href="activity.php" class="<?= $currentPage === 'activity.php' ? 'nav-active' : '' ?>" aria-label="<?= htmlspecialchars(t('nav.activity')) ?>"><?= htmlspecialchars(t('nav.activity')) ?></a></li>
                    <!-- Analiza -->
                    <li><a href="analytics.php" class="<?= $currentPage === 'analytics.php' ? 'nav-active' : '' ?>" aria-label="<?= htmlspecialchars(t('nav.analytics')) ?>"><?= htmlspecialchars(t('nav.analytics')) ?></a></li>
                    <!-- Lista życzeń -->
                    <li><a href="watchlist.php" class="<?= $currentPage === 'watchlist.php' ? 'nav-active' : '' ?>" aria-label="<?= htmlspecialchars(t('nav.watchlist')) ?>"><?= htmlspecialchars(t('nav.watchlist')) ?></a></li>
                    <!-- Kalendarz premier -->
                    <li><a href="releases_calendar.php" class="<?= $currentPage === 'releases_calendar.php' ? 'nav-active' : '' ?>" aria-label="<?= htmlspecialchars(t('nav.calendar')) ?>"><?= htmlspecialchars(t('nav.calendar')) ?></a></li>
                    <!-- Afisza (widzą wszyscy zalogowani) -->
                    <li><a href="afisha.php" class="<?= $currentPage === 'afisha.php' ? 'nav-active' : '' ?>" aria-label="<?= htmlspecialchars(t('nav.afisha')) ?>"><?= htmlspecialchars(t('nav.afisha')) ?></a></li>

                    <!-- Znajomi (widzą wszyscy) -->
                    <li>
                        <a href="friends.php" class="<?= $currentPage === 'friends.php' ? 'nav-active' : '' ?>" style="position: relative;">
                            <?= htmlspecialchars(t('nav.friends')) ?>
                            <?php if ($pendingRequestsCount > 0): ?>
                                <span class="nav-badge" id="friends-badge"><?= $pendingRequestsCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Społeczność (WIDZI TYLKO ADMIN) -->
                    <?php if ($isAdmin): ?>
                        <li><a href="community.php" class="<?= $currentPage === 'community.php' ? 'nav-active' : '' ?>"><?= htmlspecialchars(t('nav.community')) ?></a></li>
                    <?php endif; ?>

                    <!-- Przycisk dodawania -->
                    <li><a href="add_item.php" class="<?= $currentPage === 'add_item.php' ? 'nav-active' : '' ?>"><?= htmlspecialchars(t('nav.add')) ?></a></li>
                    
                    <!-- Przycisk Admina (tylko dla admina) -->
                    <?php if ($isAdmin): ?>
                        <li class="admin-menu-item"><a href="admin.php" class="<?= $currentPage === 'admin.php' ? 'nav-active' : '' ?>" style="color: #e17055;"><?= htmlspecialchars(t('nav.admin')) ?></a></li>
                    <?php endif; ?>
                    
                    <!-- Przełącznik języka -->
                    <li class="lang-switcher">
                        <form method="GET" action="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?>" style="margin:0; display:flex; align-items:center; gap:5px;">
                            <?php
                            // Zapisujemy wszystkie aktualne parametry GET, oprócz lang
                            foreach ($_GET as $key => $value) {
                                if ($key === 'lang') continue;
                                if (is_array($value)) continue;
                                echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                            }
                            ?>
                            <select name="lang" class="lang-select" onchange="this.form.submit()">
                                <option value="pl" <?= $currentLang === 'pl' ? 'selected' : '' ?>>🇵🇱 PL</option>
                                <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>🇬🇧 EN</option>
                                <option value="ru" <?= $currentLang === 'ru' ? 'selected' : '' ?>>🇷🇺 RU</option>
                            </select>
                        </form>
                    </li>
                    
                    <!-- Profil użytkownika -->
                    <li>
                        <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'nav-active' : '' ?>" style="display: flex; align-items: center;">
                            <?php if ($avatarUrl): ?>
                                <img src="<?= htmlspecialchars($avatarUrl) ?>" class="nav-avatar" alt="Avatar">
                            <?php else: ?>
                                <div class="nav-avatar" style="background: #dfe6e9; display: flex; align-items: center; justify-content: center; color: #636e72; font-weight: bold;">
                                    <?= htmlspecialchars(strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <!-- Obcinamy imię, jeśli jest zbyt długie, aby nie łamać menu na telefonie -->
                            <span style="max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                            </span>
                        </a>
                    </li>
                    
                    <li>
                        <form action="logout.php" method="POST" style="margin: 0; display: inline;">
                            <?= csrf_input(); ?>
                            <button type="submit" class="btn-logout" title="<?= htmlspecialchars(t('nav.logout')) ?>">
                                <?= htmlspecialchars(t('nav.logout')) ?>
                            </button>
                        </form>
                    </li>

                <?php else: ?>
                    <!-- Menu dla gości -->
                    <li class="lang-switcher">
                        <form method="GET" action="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?>" style="margin:0; display:flex; align-items:center; gap:5px;">
                            <?php
                            foreach ($_GET as $key => $value) {
                                if ($key === 'lang') continue;
                                if (is_array($value)) continue;
                                echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                            }
                            ?>
                            <select name="lang" class="lang-select" onchange="this.form.submit()">
                                <option value="pl" <?= $currentLang === 'pl' ? 'selected' : '' ?>>🇵🇱 PL</option>
                                <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>🇬🇧 EN</option>
                                <option value="ru" <?= $currentLang === 'ru' ? 'selected' : '' ?>>🇷🇺 RU</option>
                            </select>
                        </form>
                    </li>
                    <li>
                        <div class="toggle-switch">
                            <label>
                                <input type="checkbox" id="theme-toggle" onchange="toggleTheme()" aria-label="<?= htmlspecialchars(t('nav.toggle_theme') ?? 'Переключить тему') ?>" aria-pressed="false">
                                <span class="slider" role="switch" aria-label="<?= htmlspecialchars(t('nav.toggle_theme') ?? 'Переключить тему') ?>"></span>
                            </label>
                        </div>
                    </li>
                    <li><a href="login.php" class="<?= $currentPage === 'login.php' ? 'nav-active' : '' ?>" style="font-weight: bold;" aria-label="<?= htmlspecialchars(t('nav.login')) ?>"><?= htmlspecialchars(t('nav.login')) ?></a></li>
                    <li><a href="register.php" class="btn-register"><?= htmlspecialchars(t('nav.register')) ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <main class="container">