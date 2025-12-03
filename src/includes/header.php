<?php
// src/includes/header.php

require_once __DIR__ . '/init.php';

$avatarUrl = null;
$myId = $_SESSION['user_id'] ?? 0;
$isAdmin = !empty($_SESSION['is_admin']);

if ($myId) {
    $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
    $stmt->execute([$myId]);
    $avatarUrl = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <!-- Важный тег для мобильной адаптивности -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <title><?= htmlspecialchars(t('site.title')) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>
        window.csrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <span style="font-size: 1.8rem;">🍿</span> <?= htmlspecialchars(t('site.title')) ?>
            </a>
            <ul class="nav-links">
                <?php if ($myId): ?>
                    <!-- Афиша (видят все залогиненные) -->
                    <li><a href="afisha.php"><?= htmlspecialchars(t('nav.afisha')) ?></a></li>

                    <!-- Друзья (видят все) -->
                    <li><a href="friends.php"><?= htmlspecialchars(t('nav.friends')) ?></a></li>
                    
                    <!-- Сообщество (ВИДИТ ТОЛЬКО АДМИН) -->
                    <?php if ($isAdmin): ?>
                        <li><a href="community.php"><?= htmlspecialchars(t('nav.community')) ?></a></li>
                    <?php endif; ?>

                    <!-- Кнопка добавления -->
                    <li><a href="add_item.php"><?= htmlspecialchars(t('nav.add')) ?></a></li>
                    
                    <!-- Кнопка Админа (только для админа) -->
                    <?php if ($isAdmin): ?>
                        <li><a href="admin.php" style="color: #e17055;"><?= htmlspecialchars(t('nav.admin')) ?></a></li>
                    <?php endif; ?>
                    
                    <!-- Переключатель языка -->
                    <li class="lang-switcher">
                        <select onchange="(function(){const url=new URL(window.location);url.searchParams.set('lang',this.value);window.location.replace(url.toString());}).call(this)" class="lang-select">
                            <option value="pl" <?= $currentLang === 'pl' ? 'selected' : '' ?>>🇵🇱 PL</option>
                            <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>🇬🇧 EN</option>
                            <option value="ru" <?= $currentLang === 'ru' ? 'selected' : '' ?>>🇷🇺 RU</option>
                        </select>
                    </li>
                    
                    <!-- Профиль пользователя -->
                    <li>
                        <a href="profile.php" style="display: flex; align-items: center;">
                            <?php if ($avatarUrl): ?>
                                <img src="<?= htmlspecialchars($avatarUrl) ?>" class="nav-avatar" alt="Avatar">
                            <?php else: ?>
                                <div class="nav-avatar" style="background: #dfe6e9; display: flex; align-items: center; justify-content: center; color: #636e72; font-weight: bold;">
                                    <?= htmlspecialchars(strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <!-- Обрезаем имя, если оно слишком длинное, чтобы не ломать меню на телефоне -->
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
                    <!-- Меню для гостей -->
                    <li class="lang-switcher">
                        <select onchange="(function(){const url=new URL(window.location);url.searchParams.set('lang',this.value);window.location.replace(url.toString());}).call(this)" class="lang-select">
                            <option value="pl" <?= $currentLang === 'pl' ? 'selected' : '' ?>>🇵🇱 PL</option>
                            <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>🇬🇧 EN</option>
                            <option value="ru" <?= $currentLang === 'ru' ? 'selected' : '' ?>>🇷🇺 RU</option>
                        </select>
                    </li>
                    <li><a href="login.php" style="font-weight: bold;"><?= htmlspecialchars(t('nav.login')) ?></a></li>
                    <li><a href="register.php" class="btn-register"><?= htmlspecialchars(t('nav.register')) ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <main class="container">