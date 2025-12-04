<?php
// src/releases_calendar.php - Календарь релизов фильмов

require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Получаем фильмы из афиши с датами релиза в выбранном месяце
$stmt = $pdo->prepare("
    SELECT id, title, poster_url, release_date, overview
    FROM upcoming_movies
    WHERE EXTRACT(YEAR FROM release_date) = ?
      AND EXTRACT(MONTH FROM release_date) = ?
      AND release_date >= CURRENT_DATE
    ORDER BY release_date ASC
");
$stmt->execute([$year, $month]);
$releases = $stmt->fetchAll();

// Группируем по датам
$releasesByDate = [];
foreach ($releases as $release) {
    $date = date('Y-m-d', strtotime($release['release_date']));
    if (!isset($releasesByDate[$date])) {
        $releasesByDate[$date] = [];
    }
    $releasesByDate[$date][] = $release;
}

// Навигация по месяцам
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthNames = [
    1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
    5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
    9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
];

require_once 'includes/header.php';
?>

<div class="dashboard">
    <div class="header-actions">
        <h2><?= htmlspecialchars(t('calendar.title') ?? 'Календарь релизов') ?></h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="releases_calendar.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn-register" style="text-decoration: none;">←</a>
            <span style="font-weight: bold; min-width: 200px; text-align: center;">
                <?= htmlspecialchars($monthNames[$month] ?? $month) ?> <?= $year ?>
            </span>
            <a href="releases_calendar.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn-register" style="text-decoration: none;">→</a>
        </div>
    </div>

    <?php if (empty($releasesByDate)): ?>
        <div class="empty-state">
            <p><?= htmlspecialchars(t('calendar.no_releases') ?? 'Нет релизов в этом месяце') ?></p>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 20px;">
            <?php foreach ($releasesByDate as $date => $dateReleases): ?>
                <div style="background: white; padding: 20px; border-radius: 15px; box-shadow: var(--shadow);">
                    <h3 style="color: var(--primary); margin-bottom: 15px;">
                        <?= htmlspecialchars(date('d.m.Y', strtotime($date))) ?>
                    </h3>
                    <div class="media-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));">
                        <?php foreach ($dateReleases as $release): ?>
                            <div class="media-card">
                                <div class="media-image">
                                    <?php if (!empty($release['poster_url'])): ?>
                                        <img src="<?= htmlspecialchars($release['poster_url']) ?>" alt="Poster">
                                    <?php else: ?>
                                        <div class="no-image">No poster</div>
                                    <?php endif; ?>
                                </div>
                                <div class="media-content">
                                    <h3 style="font-size: 0.9rem;"><?= htmlspecialchars($release['title']) ?></h3>
                                    <form method="POST" action="afisha_add.php" style="margin-top: 10px;">
                                        <?= csrf_input(); ?>
                                        <input type="hidden" name="upcoming_id" value="<?= (int)$release['id'] ?>">
                                        <button type="submit" class="afisha-add-btn" style="font-size: 0.8rem; padding: 8px;">
                                            + Добавить
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

