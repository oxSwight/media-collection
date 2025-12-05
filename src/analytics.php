<?php
// src/analytics.php - Расширенная аналитика коллекции

require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Получаем все данные коллекции пользователя
$stmt = $pdo->prepare("
    SELECT type, release_year, rating, genres, created_at
    FROM media_items
    WHERE user_id = ?
    ORDER BY release_year ASC, created_at ASC
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

// Статистика по годам
$yearsData = [];
$yearsRatings = [];
foreach ($items as $item) {
    if (!empty($item['release_year']) && $item['release_year'] >= 1900) {
        $year = (int)$item['release_year'];
        if (!isset($yearsData[$year])) {
            $yearsData[$year] = 0;
            $yearsRatings[$year] = [];
        }
        $yearsData[$year]++;
        if (!empty($item['rating'])) {
            $yearsRatings[$year][] = (int)$item['rating'];
        }
    }
}

// Средние оценки по годам
$avgRatingsByYear = [];
foreach ($yearsRatings as $year => $ratings) {
    if (!empty($ratings)) {
        $avgRatingsByYear[$year] = round(array_sum($ratings) / count($ratings), 2);
    }
}

// Статистика по жанрам
$genresData = [];
foreach ($items as $item) {
    if (!empty($item['genres'])) {
        $genres = preg_split('/[,\s]+/', $item['genres']);
        foreach ($genres as $genre) {
            $genre = trim($genre);
            if ($genre !== '') {
                $genresData[$genre] = ($genresData[$genre] ?? 0) + 1;
            }
        }
    }
}
arsort($genresData);
$topGenres = array_slice($genresData, 0, 10, true);

// Статистика по типам
$typesData = ['movie' => 0, 'book' => 0];
foreach ($items as $item) {
    if (isset($typesData[$item['type']])) {
        $typesData[$item['type']]++;
    }
}

// Общая статистика
$totalItems = count($items);
$avgRating = 0;
$ratings = array_filter(array_column($items, 'rating'));
if (!empty($ratings)) {
    $avgRating = round(array_sum($ratings) / count($ratings), 2);
}

require_once 'includes/header.php';
?>

<div class="dashboard">
    <h2><?= htmlspecialchars(t('analytics.title')) ?></h2>
    
    <div class="analytics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="analytics-card">
            <h3 class="analytics-card-title"><?= htmlspecialchars(t('analytics.total_items')) ?></h3>
            <div class="analytics-card-value"><?= $totalItems ?></div>
        </div>
        
        <div class="analytics-card">
            <h3 class="analytics-card-title"><?= htmlspecialchars(t('analytics.avg_rating')) ?></h3>
            <div class="analytics-card-value"><?= $avgRating ?: '-' ?></div>
        </div>
        
        <div class="analytics-card">
            <h3 class="analytics-card-title"><?= htmlspecialchars(t('analytics.movies')) ?></h3>
            <div class="analytics-card-value"><?= $typesData['movie'] ?></div>
        </div>
        
        <div class="analytics-card">
            <h3 class="analytics-card-title"><?= htmlspecialchars(t('analytics.books')) ?></h3>
            <div class="analytics-card-value"><?= $typesData['book'] ?></div>
        </div>
    </div>

    <!-- График по годам выпуска -->
    <?php if (!empty($yearsData)): ?>
    <div class="analytics-section">
        <h3 class="analytics-section-title"><?= htmlspecialchars(t('analytics.by_year')) ?></h3>
        <canvas id="yearsChart" style="max-height: 400px;"></canvas>
    </div>
    <?php endif; ?>

    <!-- График средних оценок по годам -->
    <?php if (!empty($avgRatingsByYear)): ?>
    <div class="analytics-section">
        <h3 class="analytics-section-title"><?= htmlspecialchars(t('analytics.avg_rating_by_year')) ?></h3>
        <canvas id="ratingsChart" style="max-height: 400px;"></canvas>
    </div>
    <?php endif; ?>

    <!-- Статистика по жанрам -->
    <?php if (!empty($topGenres)): ?>
    <div class="analytics-section">
        <h3 class="analytics-section-title"><?= htmlspecialchars(t('analytics.by_genres')) ?></h3>
        <canvas id="genresChart" style="max-height: 400px;"></canvas>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// График по годам выпуска
<?php if (!empty($yearsData)): ?>
const yearsCtx = document.getElementById('yearsChart');
if (yearsCtx) {
    const yearsData = <?= json_encode($yearsData) ?>;
    const years = Object.keys(yearsData).sort((a, b) => a - b);
    const counts = years.map(y => yearsData[y]);
    
    new Chart(yearsCtx, {
        type: 'bar',
        data: {
            labels: years,
            datasets: [{
                label: '<?= htmlspecialchars(t('analytics.items_count')) ?>',
                data: counts,
                backgroundColor: 'rgba(108, 92, 231, 0.6)',
                borderColor: 'rgba(108, 92, 231, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// График средних оценок по годам
<?php if (!empty($avgRatingsByYear)): ?>
const ratingsCtx = document.getElementById('ratingsChart');
if (ratingsCtx) {
    const avgRatings = <?= json_encode($avgRatingsByYear) ?>;
    const years = Object.keys(avgRatings).sort((a, b) => a - b);
    const ratings = years.map(y => avgRatings[y]);
    
    new Chart(ratingsCtx, {
        type: 'line',
        data: {
            labels: years,
            datasets: [{
                label: '<?= htmlspecialchars(t('analytics.avg_rating')) ?>',
                data: ratings,
                borderColor: 'rgba(253, 121, 168, 1)',
                backgroundColor: 'rgba(253, 121, 168, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 0,
                    max: 10,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// График по жанрам
<?php if (!empty($topGenres)): ?>
const genresCtx = document.getElementById('genresChart');
if (genresCtx) {
    const genresData = <?= json_encode($topGenres) ?>;
    const genres = Object.keys(genresData);
    const counts = genres.map(g => genresData[g]);
    
    new Chart(genresCtx, {
        type: 'doughnut',
        data: {
            labels: genres,
            datasets: [{
                data: counts,
                backgroundColor: [
                    'rgba(108, 92, 231, 0.8)',
                    'rgba(253, 121, 168, 0.8)',
                    'rgba(255, 184, 0, 0.8)',
                    'rgba(0, 206, 201, 0.8)',
                    'rgba(225, 112, 85, 0.8)',
                    'rgba(116, 185, 255, 0.8)',
                    'rgba(162, 155, 254, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>

