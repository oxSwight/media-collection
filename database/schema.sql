-- ============================================
-- ПОЛНАЯ СХЕМА БАЗЫ ДАННЫХ ДЛЯ MEDIA COLLECTION
-- PostgreSQL
-- ============================================

-- 1. ТАБЛИЦА ПОЛЬЗОВАТЕЛЕЙ
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    friend_code VARCHAR(10) UNIQUE,
    is_admin BOOLEAN NOT NULL DEFAULT FALSE,
    avatar_path VARCHAR(255),
    bio TEXT,
    visibility VARCHAR(20) NOT NULL DEFAULT 'friends' CHECK (visibility IN ('public', 'friends', 'private')),
    reset_token VARCHAR(255),
    reset_expires TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Индексы для таблицы users
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_friend_code ON users(friend_code);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- 2. ТАБЛИЦА МЕДИА-КОЛЛЕКЦИИ
CREATE TABLE IF NOT EXISTS media_items (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(150) NOT NULL,
    type VARCHAR(10) NOT NULL CHECK (type IN ('movie', 'book')),
    author_director VARCHAR(100),
    release_year INTEGER,
    rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 10),
    image_path VARCHAR(255),
    review TEXT,
    genres TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Индексы для таблицы media_items
CREATE INDEX IF NOT EXISTS idx_media_title ON media_items(title);
CREATE INDEX IF NOT EXISTS idx_media_user_created ON media_items(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_media_type ON media_items(type);
CREATE INDEX IF NOT EXISTS idx_media_user_type ON media_items(user_id, type);
CREATE INDEX IF NOT EXISTS idx_media_release_year ON media_items(release_year);
-- Индекс для поиска по названию (case-insensitive) создается отдельно, если нужен
-- CREATE INDEX IF NOT EXISTS idx_media_user_title_lower ON media_items(user_id, (LOWER(title)));

-- 3. ТАБЛИЦА ЛАЙКОВ
CREATE TABLE IF NOT EXISTS likes (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    media_id INTEGER NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, media_id)
);

-- Индексы для таблицы likes
CREATE INDEX IF NOT EXISTS idx_likes_media ON likes(media_id);
CREATE INDEX IF NOT EXISTS idx_likes_user ON likes(user_id);
CREATE INDEX IF NOT EXISTS idx_likes_created ON likes(created_at DESC);

-- 4. ТАБЛИЦА ДРУЖБЫ
CREATE TABLE IF NOT EXISTS friendships (
    id SERIAL PRIMARY KEY,
    requester_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'rejected')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(requester_id, receiver_id)
);

-- Индексы для таблицы friendships
CREATE INDEX IF NOT EXISTS idx_friendships_requester ON friendships(requester_id, status);
CREATE INDEX IF NOT EXISTS idx_friendships_receiver ON friendships(receiver_id, status);
CREATE INDEX IF NOT EXISTS idx_friendships_status ON friendships(status);

-- 5. ТАБЛИЦА АКТИВНОСТИ
CREATE TABLE IF NOT EXISTS activities (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,
    media_id INTEGER REFERENCES media_items(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Индексы для таблицы activities
CREATE INDEX IF NOT EXISTS idx_activities_user ON activities(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_activities_type ON activities(type);
CREATE INDEX IF NOT EXISTS idx_activities_media ON activities(media_id);
CREATE INDEX IF NOT EXISTS idx_activities_created ON activities(created_at DESC);

-- 6. ТАБЛИЦА ПРЕДСТОЯЩИХ ФИЛЬМОВ (АФИША)
CREATE TABLE IF NOT EXISTS upcoming_movies (
    id SERIAL PRIMARY KEY,
    external_id VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255),
    original_title VARCHAR(255),
    overview TEXT,
    poster_url VARCHAR(500),
    release_date DATE,
    genres VARCHAR(255),
    popularity NUMERIC(10, 2),
    vote_average NUMERIC(3, 1),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Индексы для таблицы upcoming_movies
CREATE INDEX IF NOT EXISTS idx_upcoming_external_id ON upcoming_movies(external_id);
CREATE INDEX IF NOT EXISTS idx_upcoming_release_date ON upcoming_movies(release_date);
CREATE INDEX IF NOT EXISTS idx_upcoming_title ON upcoming_movies(title);
CREATE INDEX IF NOT EXISTS idx_upcoming_updated ON upcoming_movies(updated_at DESC);

-- 7. ТАБЛИЦА СПИСКА ЖЕЛАНИЙ (WATCHLIST)
CREATE TABLE IF NOT EXISTS watchlist (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    upcoming_movie_id INTEGER REFERENCES upcoming_movies(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'movie' CHECK (type IN ('movie', 'book')),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, upcoming_movie_id)
);

-- Индексы для таблицы watchlist
CREATE INDEX IF NOT EXISTS idx_watchlist_user ON watchlist(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_watchlist_movie ON watchlist(upcoming_movie_id);
CREATE INDEX IF NOT EXISTS idx_watchlist_type ON watchlist(type);

-- ============================================
-- КОММЕНТАРИИ К ТАБЛИЦАМ
-- ============================================

COMMENT ON TABLE users IS 'Пользователи системы';
COMMENT ON TABLE media_items IS 'Медиа-коллекция пользователей (фильмы и книги)';
COMMENT ON TABLE likes IS 'Лайки пользователей на медиа-элементы';
COMMENT ON TABLE friendships IS 'Дружеские связи между пользователями';
COMMENT ON TABLE activities IS 'Лента активности пользователей';
COMMENT ON TABLE upcoming_movies IS 'Предстоящие фильмы из TMDb API';
COMMENT ON TABLE watchlist IS 'Список желаний пользователей';
