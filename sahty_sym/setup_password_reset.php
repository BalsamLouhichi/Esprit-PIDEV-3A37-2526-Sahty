#!/usr/bin/env php
<?php

try {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists(\Symfony\Component\Dotenv\Dotenv::class)) {
        (new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__ . '/.env');
    }

    $databaseUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? '');
    if ($databaseUrl === '') {
        throw new RuntimeException('DATABASE_URL introuvable.');
    }

    $parts = parse_url($databaseUrl);
    if ($parts === false || !isset($parts['host'], $parts['path'])) {
        throw new RuntimeException('DATABASE_URL invalide.');
    }

    $dbName = ltrim((string) $parts['path'], '/');
    if ($dbName === '') {
        throw new RuntimeException('Nom de base manquant dans DATABASE_URL.');
    }

    parse_str((string) ($parts['query'] ?? ''), $query);
    $charset = $query['charset'] ?? 'utf8mb4';
    $port = (int) ($parts['port'] ?? 3306);
    $user = urldecode((string) ($parts['user'] ?? 'root'));
    $pass = urldecode((string) ($parts['pass'] ?? ''));

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string) $parts['host'],
        $port,
        $dbName,
        $charset
    );

    $pdo = new PDO($dsn, $user, $pass);

    echo "Connected to database\n";

    $sql = "CREATE TABLE IF NOT EXISTS password_reset_token (
        id INT AUTO_INCREMENT NOT NULL,
        utilisateur_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        is_used TINYINT(1) DEFAULT 0 NOT NULL,
        UNIQUE INDEX UNIQ_TOKEN (token),
        INDEX IDX_UTILISATEUR (utilisateur_id),
        PRIMARY KEY(id),
        CONSTRAINT FK_PASSWORD_RESET_TOKEN_UTILISATEUR FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "Table password_reset_token is ready\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
