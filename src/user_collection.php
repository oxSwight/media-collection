<?php
// src/user_collection.php
require_once 'includes/init.php';
require_once 'includes/pagination.php';

$targetUserId = (int)($_GET['id'] ?? 0);
$myId = $_SESSION['user_id'] ?? 0;

if (!$myId) {
    header("Location: login.php");
    exit;
}

if ($targetUserId === $myId) {
    header("Location: index.php");
    exit;
}

// 3. Получаем данные о владельце коллекции, включая приватность
$userStmt = $pdo->prepare("SELECT username, avatar_path, bio, visibility FROM users WHERE id = ?");
$userStmt->execute([$targetUserId]);
$owner = $userStmt->fetch();

// Если такого пользователя нет или коллекция скрыта
if (!$owner) {
    require_once 'includes/header.php';
    echo "<div class='container' style='text-align:center; padding:50px;'>
            <h2>" . htmlspecialchars(t('user_collection.user_not_found')) . "</h2>
            <a href='community.php' class='btn-submit' style='width:auto;'>" . htmlspecialchars(t('user_collection.back_community')) . "</a>
          </div>";
    require_once 'includes/footer.php';
    exit;
}

// Проверка приватности: если профиль приватный / только для друзей
$visibility = $owner['visibility'] ?? 'friends';
if ($visibility === 'private') {
    require_once 'includes/header.php';
    echo "<div class='container' style='text-align:center; padding:50px;'>
            <h2>" . htmlspecialchars(t('user_collection.user_not_found')) . "</h2>
            <a href='community.php' class='btn-submit' style='width:auto;'>" . htmlspecialchars(t('user_collection.back_community')) . "</a>
          </div>";
    require_once 'includes/footer.php';
    exit;
}

// visibility = friends: показываем только друзьям или админу
if ($visibility === 'friends' && empty($_SESSION['is_admin'])) {
    $friendCheck = $pdo->prepare("
        SELECT 1 FROM friendships 
        WHERE status = 'accepted' 
          AND ((requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?))
        LIMIT 1
    ");
    $friendCheck->execute([$myId, $targetUserId, $targetUserId, $myId]);
    if (!$friendCheck->fetchColumn()) {
        require_once 'includes/header.php';
        echo "<div class='container' style='text-align:center; padding:50px;'>
                <h2>" . htmlspecialchars(t('user_collection.user_not_found')) . "</h2>
                <a href='community.php' class='btn-submit' style='width:auto;'>" . htmlspecialchars(t('user_collection.back_community')) . "</a>
              </div>";
        require_once 'includes/footer.php';
        exit;
    }
}

// 4. Получаем параметры пагинации
$pagination = get_pagination_params(20);
$page = $pagination['page'];
$perPage = $pagination['per_page'];
$offset = $pagination['offset'];

// 5. Считаем общее количество элементов
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM media_items WHERE user_id = ?");
$countStmt->execute([$targetUserId]);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

// 6. Оптимизированный запрос с JOIN вместо подзапросов (убираем N+1)
$sql = "
    SELECT 
        m.*,
        COALESCE(like_counts.likes_count, 0) as likes_count,
        CASE WHEN my_likes.media_id IS NOT NULL THEN 1 ELSE 0 END as is_liked_by_me
    FROM media_items m
    LEFT JOIN (
        SELECT media_id, COUNT(*) as likes_count
        FROM likes
        GROUP BY media_id
    ) like_counts ON m.id = like_counts.media_id
    LEFT JOIN (
        SELECT media_id
        FROM likes
        WHERE user_id = ?
    ) my_likes ON m.id = my_likes.media_id
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$myId, $targetUserId, $perPage, $offset]);
$items = $stmt->fetchAll();

// 7. Генерируем HTML пагинации
$paginationHtml = '';
if ($totalPages > 1) {
    $paginationHtml = render_pagination($page, $totalPages, 'user_collection.php');
}

// Подключаем шапку только сейчас, когда все проверки прошли
require_once 'includes/header.php';
?>

<!-- ШАПКА ПРОФИЛЯ -->
<div class="profile-header">
    <?php if ($owner['avatar_path']): ?>
        <img src="<?= htmlspecialchars($owner['avatar_path']) ?>" class="profile-avatar-large">
    <?php else: ?>
        <div class="profile-avatar-large" style="background: #dfe6e9; display: flex; align-items: center; justify-content: center; font-size: 3rem;">
            <?= htmlspecialchars(strtoupper(substr($owner['username'], 0, 1))) ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-info">
        <span style="text-transform: uppercase; color: #6c5ce7; font-weight: bold; font-size: 0.8rem;"><?= htmlspecialchars(t('user_collection.collection')) ?></span>
        <h1 style="margin-top: 5px; margin-bottom: 10px;"><?= htmlspecialchars($owner['username']) ?></h1>
        <?php if ($owner['bio']): ?>
            <p style="color: #636e72; max-width: 600px; line-height: 1.5;"><?= nl2br(htmlspecialchars($owner['bio'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- СЕТКА ФИЛЬМОВ -->
<?php if ($totalItems === 0): ?>
    <div class="empty-state">
        <p><?= htmlspecialchars(t('user_collection.no_items')) ?></p>
        <a href="community.php" class="btn-submit" style="display: inline-block; width: auto; text-decoration: none;"><?= htmlspecialchars(t('user_collection.back_community')) ?></a>
    </div>
<?php else: ?>
    <?php if ($totalItems > 0): ?>
        <div style="margin-bottom: 15px; color: #636e72; font-size: 0.9rem;">
            <?= htmlspecialchars(t('user_collection.collection')) ?> <strong><?= $totalItems ?></strong> 
            <?php
            $itemsKey = 'collection.items';
            if ($totalItems >= 5) $itemsKey = 'collection.items_5plus';
            elseif ($totalItems >= 2) $itemsKey = 'collection.items_2_4';
            echo htmlspecialchars(t($itemsKey));
            ?>
            <?php if ($totalPages > 1): ?>
                | <?= htmlspecialchars(t('collection.page')) ?> <strong><?= $page ?></strong> <?= htmlspecialchars(t('collection.of')) ?> <strong><?= $totalPages ?></strong>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="media-grid">
        <?php foreach ($items as $item): ?>
            <div class="media-card" 
                 onclick="openModal(this)"
                 style="cursor: pointer;"
                 data-title="<?= htmlspecialchars($item['title']) ?>"
                 data-type="<?= $item['type'] === 'movie' ? htmlspecialchars(t('collection.movies')) : htmlspecialchars(t('collection.books')) ?>"
                 data-author="<?= htmlspecialchars($item['author_director']) ?>"
                 data-year="<?= $item['release_year'] ?>"
                 data-review="<?= htmlspecialchars($item['review']) ?>"
                 data-image="<?= htmlspecialchars($item['image_path'] ?? '') ?>"
                 data-rating="<?= htmlspecialchars((string)$item['rating']) ?>">

                <div class="media-image">
                    <?php 
                    $imagePath = $item['image_path'] ?? '';
                    // Проверяем, существует ли файл, если это локальный путь
                    $imageExists = false;
                    if (!empty($imagePath)) {
                        if (strpos($imagePath, 'http') === 0) {
                            // Это внешний URL - считаем, что он существует
                            $imageExists = true;
                        } else {
                            // Это локальный путь - проверяем существование файла
                            $fullPath = __DIR__ . '/' . ltrim($imagePath, '/');
                            $imageExists = file_exists($fullPath);
                        }
                    }
                    ?>
                    <?php if ($imageExists): ?>
                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="Okładka" onerror="this.parentElement.innerHTML='<div class=\'no-image\'>' + '<?= htmlspecialchars(t('item.image')) ?>' + '</div>'">
                    <?php else: ?>
                        <div class="no-image"><?= htmlspecialchars(t('item.image')) ?></div>
                    <?php endif; ?>
                    <div class="media-rating"><?= htmlspecialchars((string)$item['rating']) ?>/10</div>

                    <!-- КНОПКА ЛАЙКА -->
                    <button class="like-btn <?= $item['is_liked_by_me'] ? 'liked' : '' ?>" 
                            onclick="toggleLike(event, <?= (int)$item['id'] ?>)">
                        <span class="like-icon">❤</span> 
                        <span class="like-count"><?= htmlspecialchars((string)$item['likes_count']) ?></span>
                    </button>
                </div>
                
                <div class="media-content">
                    <span class="media-type type-<?= htmlspecialchars($item['type']) ?>"><?= $item['type'] === 'movie' ? htmlspecialchars(t('collection.movies')) : htmlspecialchars(t('collection.books')) ?></span>
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <div class="media-meta"><?= htmlspecialchars($item['author_director']) ?> (<?= htmlspecialchars((string)$item['release_year']) ?>)</div>
                    
                    <?php if (!empty($item['review'])): ?>
                        <p class="media-review">
                            <?= nl2br(htmlspecialchars(mb_strimwidth($item['review'], 0, 80, "..."))) ?>
                            <br><span style="color: var(--primary); font-size: 0.8rem; font-weight: bold;"><?= htmlspecialchars(t('user_collection.read_more')) ?></span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?= $paginationHtml ?>
<?php endif; ?>

<!-- МОДАЛЬНОЕ ОКНО (Обновленное) -->
<div id="mediaModal" class="modal-overlay" onclick="closeModal(event)">
    <div class="modal-content">
        <!-- Кнопка закрытия -->
        <div class="modal-close" onclick="closeModalDirect()">&times;</div>
        
        <!-- Обертка для фото -->
        <div class="modal-image-wrapper" id="imgWrapper" style="display: none;">
            <img id="mImage" class="modal-image-large">
        </div>
        
        <!-- Контент -->
        <div class="modal-body">
            <div class="modal-header-row">
                <div>
                    <span id="mType" class="media-type" style="margin-bottom: 5px;"></span>
                    <h2 id="mTitle" class="modal-title"></h2>
                    <p id="mAuthor" style="color: #636e72; margin: 5px 0 0 0; font-weight: 500;"></p>
                </div>
                <div id="mRating" style="font-weight: 800; color: #fdcb6e; background: #2d3436; padding: 5px 10px; border-radius: 10px; font-size: 1.1rem; white-space: nowrap;"></div>
            </div>

            <hr style="border: 0; border-top: 1px solid #f1f2f6; margin: 15px 0;">
            
            <h4 style="margin: 0 0 10px 0; color: #b2bec3; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;"><?= htmlspecialchars(t('item.review')) ?></h4>
            <div id="mReview" style="line-height: 1.6; color: #2d3436; font-size: 1rem;"></div>
        </div>
    </div>
</div>

<!-- СКРИПТЫ -->
<script>
// Лайки с улучшенной обработкой ошибок и loading state
async function toggleLike(e, mediaId) {
    e.stopPropagation(); // Чтобы не открывалось окно при клике на лайк
    const btn = e.currentTarget;
    const countSpan = btn.querySelector('.like-count');
    const icon = btn.querySelector('.like-icon');
    
    // Блокируем кнопку во время запроса
    if (btn.disabled) return;
    btn.disabled = true;
    btn.style.opacity = '0.6';
    btn.style.cursor = 'wait';

    // Анимация
    icon.classList.add('like-anim');
    setTimeout(() => icon.classList.remove('like-anim'), 300);

    try {
        // Запрос
        const headers = { 'Content-Type': 'application/json' };
        if (window.csrfToken) {
            headers['X-CSRF-TOKEN'] = window.csrfToken;
        }

        const res = await fetch('api_like.php', {
            method: 'POST',
            headers,
            body: JSON.stringify({ id: mediaId })
        });
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const data = await res.json();
        
        if (data.success) {
            if (data.action === 'liked') {
                btn.classList.add('liked');
            } else {
                btn.classList.remove('liked');
            }
            countSpan.textContent = data.count;
        } else {
            const errorMsg = data.error || 'Nieznany błąd';
            const lang = document.documentElement.lang;
            const messages = {
                'ru': 'Ошибка: ' + errorMsg,
                'pl': 'Błąd: ' + errorMsg,
                'en': 'Error: ' + errorMsg
            };
            alert(messages[lang] || messages['en']);
        }
    } catch (error) {
        console.error('Error toggling like:', error);
        const lang = document.documentElement.lang;
        const messages = {
            'ru': 'Произошла ошибка. Пожалуйста, попробуйте еще раз.',
            'pl': 'Wystąpił błąd. Spróbuj ponownie.',
            'en': 'An error occurred. Please try again.'
        };
        alert(messages[lang] || messages['en']);
    } finally {
        // Разблокируем кнопку
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
    }
}

// Модальное окно
function openModal(card) {
    const title = card.getAttribute('data-title');
    const type = card.getAttribute('data-type');
    const author = card.getAttribute('data-author');
    const year = card.getAttribute('data-year');
    const review = card.getAttribute('data-review');
    const image = card.getAttribute('data-image');
    const rating = card.getAttribute('data-rating');

    document.getElementById('mTitle').textContent = title;
    
    // Тип (цветной бейдж)
    const typeElem = document.getElementById('mType');
    typeElem.textContent = type;
    typeElem.className = 'media-type'; 
    // Проверяем по тексту, так как тип передается уже переведенным
    if(type.includes('🎬') || type.toLowerCase().includes('film') || type.toLowerCase().includes('movie')) {
        typeElem.classList.add('type-movie');
    } else {
        typeElem.classList.add('type-book');
    }

    document.getElementById('mAuthor').textContent = author + ' (' + year + ')';
    
    // Текст рецензии (с сохранением абзацев) - безопасно через textContent
    const reviewElem = document.getElementById('mReview');
    if (review) {
        reviewElem.textContent = '';
        const lines = review.split('\n');
        lines.forEach((line, i) => {
            if (i > 0) reviewElem.appendChild(document.createElement('br'));
            reviewElem.appendChild(document.createTextNode(line));
        });
    } else {
        reviewElem.innerHTML = '<em style="color:#b2bec3"><?= htmlspecialchars(t('user_collection.no_review'), ENT_QUOTES) ?></em>';
    }
    
    document.getElementById('mRating').textContent = rating + '/10';

    // Картинка
    const imgElem = document.getElementById('mImage');
    const imgWrapper = document.getElementById('imgWrapper');
    
    if (image) {
        imgElem.src = image;
        imgWrapper.style.display = 'flex';
    } else {
        imgWrapper.style.display = 'none';
    }

    document.getElementById('mediaModal').classList.add('open');
    document.body.style.overflow = 'hidden'; // Блокируем прокрутку страницы
}

function closeModal(event) {
    if (event.target.id === 'mediaModal') {
        closeModalDirect();
    }
}

function closeModalDirect() {
    document.getElementById('mediaModal').classList.remove('open');
    document.body.style.overflow = 'auto'; // Возвращаем прокрутку
}
</script>

<?php require_once 'includes/footer.php'; ?>