<?php
$url = getenv('DATABASE_URL');
$dbopts = parse_url($url);
$dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s", $dbopts['host'], $dbopts['port'], ltrim($dbopts['path'], '/'));

try {
    $pdo = new PDO($dsn, $dbopts['user'], $dbopts['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $sql = "
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
    CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
    CREATE INDEX IF NOT EXISTS idx_users_friend_code ON users(friend_code);
    CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

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
    CREATE INDEX IF NOT EXISTS idx_media_title ON media_items(title);
    CREATE INDEX IF NOT EXISTS idx_media_user_created ON media_items(user_id, created_at DESC);
    CREATE INDEX IF NOT EXISTS idx_media_type ON media_items(type);

    CREATE TABLE IF NOT EXISTS likes (
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        media_id INTEGER NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, media_id)
    );
    CREATE INDEX IF NOT EXISTS idx_likes_media ON likes(media_id);

    CREATE TABLE IF NOT EXISTS friendships (
        id SERIAL PRIMARY KEY,
        requester_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'rejected')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(requester_id, receiver_id)
    );

    CREATE TABLE IF NOT EXISTS activities (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        type VARCHAR(50) NOT NULL,
        media_id INTEGER REFERENCES media_items(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

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
    ";

    $pdo->exec($sql);
    echo "✅ Все таблицы и индексы успешно созданы!";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage();
}