#!/usr/bin/env php
<?php

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=sahty;charset=utf8mb4',
        'root',
        ''
    );
    
    echo "✅ Connecté à la base de données\n";
    
    // Créer la table
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
    
} catch(PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
