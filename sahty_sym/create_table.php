<?php

use Symfony\Component\Dotenv\Dotenv;

require 'vendor/autoload.php';

(new Dotenv())->bootEnv('.env');

// Créer la connexion PDO
$dsn = $_ENV['DATABASE_URL'];
// Convertir le DSN Symfony en PDO DSN
$dsn = str_replace('mysql://', 'mysql:host=', $dsn);
$dsn = str_replace(';', '?', $dsn);

try {
    $pdo = new PDO($dsn);
    
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
    echo "✅ Table password_reset_token créée avec succès\n";
} catch(Exception $e) {
    echo "État: " . $e->getMessage() . "\n";
}
