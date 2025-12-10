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

// Список уже добавленных в желания (по upcoming_id и external_id)
$watchlistUpcomingIds = [];
$watchlistExternalIds = [];
$wlStmt = $pdo->prepare("
    SELECT w.upcoming_movie_id, um.external_id
    FROM watchlist w
    LEFT JOIN upcoming_movies um ON um.id = w.upcoming_movie_id
    WHERE w.user_id = ?
");
$wlStmt->execute([$userId]);
foreach ($wlStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (!empty($row['upcoming_movie_id'])) {
        $watchlistUpcomingIds[(int)$row['upcoming_movie_id']] = true;
    }
    if (!empty($row['external_id'])) {
        $watchlistExternalIds[(string)$row['external_id']] = true;
    }
}

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

// Получаем названия месяцев из переводов
$monthNames = [];
for ($i = 1; $i <= 12; $i++) {
    $monthKey = "calendar.months.$i";
    $monthName = t($monthKey);
    // Если функция вернула сам ключ (перевод не найден), используем номер месяца
    $monthNames[$i] = ($monthName === $monthKey) ? $i : $monthName;
}

require_once 'includes/header.php';
?>

<div class="dashboard">
    <div class="header-actions">
        <h2><?= htmlspecialchars(t('calendar.title') ?? 'Календарь релизов') ?></h2>
        <div class="calendar-nav" style="display: flex; gap: 10px; align-items: center;">
            <a href="releases_calendar.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn-register calendar-nav-btn" style="text-decoration: none;">←</a>
            <span class="calendar-month-year" style="font-weight: bold; min-width: 200px; text-align: center;">
                <?= htmlspecialchars($monthNames[$month] ?? $month) ?> <?= $year ?>
            </span>
            <a href="releases_calendar.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn-register calendar-nav-btn" style="text-decoration: none;">→</a>
        </div>
    </div>

    <?php if (empty($releasesByDate)): ?>
        <div class="empty-state">
            <p><?= htmlspecialchars(t('calendar.no_releases')) ?></p>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 20px;">
            <?php foreach ($releasesByDate as $date => $dateReleases): ?>
                <div class="calendar-date-section">
                    <h3 class="calendar-date-title">
                        <?= htmlspecialchars(date('d.m.Y', strtotime($date))) ?>
                    </h3>
                    <div class="media-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));">
                        <?php foreach ($dateReleases as $release): ?>
                            <?php
                                $inWatchlist = (!empty($release['id']) && isset($watchlistUpcomingIds[(int)$release['id']]));
                            ?>
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
                                    <form method="POST" action="watchlist.php" style="margin-top: 10px;" onsubmit="return addToWatchlistCalendar(event, this);">
                                        <?= csrf_input(); ?>
                                        <input type="hidden" name="add_to_watchlist" value="1">
                                        <input type="hidden" name="upcoming_id" value="<?= (int)$release['id'] ?>">
                                        <input type="hidden" name="title" value="<?= htmlspecialchars($release['title']) ?>">
                                        <button type="submit" class="afisha-watchlist-btn" style="font-size: 0.8rem; padding: 8px;" <?= $inWatchlist ? 'disabled' : '' ?>>
                                            <?= $inWatchlist ? '✔ ' . htmlspecialchars(t('watchlist.added') ?? 'Na liście życzeń') : '⭐ ' . htmlspecialchars(t('watchlist.add') ?? 'Do listy życzeń') ?>
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

<script>
// Упрощенная функция добавления в желания для календаря
async function addToWatchlistCalendar(event, form) {
    event.preventDefault();
    event.stopPropagation();

    const button = form.querySelector('button[type="submit"]');
    const original = button.innerHTML;
    button.disabled = true;
    button.style.opacity = '0.6';
    button.style.cursor = 'wait';

    try {
        const formData = new FormData(form);
        const response = await fetch('watchlist.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            button.innerHTML = '✔ ' + (data.message || 'Na liście życzeń');
        } else {
            button.disabled = false;
            button.style.opacity = '1';
            button.style.cursor = 'pointer';
            alert(data.error || 'Błąd dodawania do listy życzeń');
        }
    } catch (e) {
        console.error(e);
        button.disabled = false;
        button.style.opacity = '1';
        button.style.cursor = 'pointer';
        alert('Błąd dodawania do listy życzeń');
    }
    return false;
}
</script>

