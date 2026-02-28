<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224090705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, type VARCHAR(30) NOT NULL, category VARCHAR(100) DEFAULT NULL, order_in_quiz INT NOT NULL, reverse TINYINT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_B6F7494E853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quiz (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quiz_attempt (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, current_question_index INT NOT NULL, answered_count INT NOT NULL, total_questions INT NOT NULL, score INT DEFAULT NULL, answers_json LONGTEXT DEFAULT NULL, detected_categories_json LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, quiz_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_AB6AFC6853CD175 (quiz_id), INDEX IDX_AB6AFC6A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE recommandation (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, tips LONGTEXT DEFAULT NULL, min_score INT NOT NULL, max_score INT NOT NULL, type_probleme VARCHAR(500) DEFAULT NULL, target_categories VARCHAR(255) DEFAULT NULL, severity VARCHAR(20) DEFAULT \'medium\' NOT NULL, created_at DATETIME NOT NULL, video_url VARCHAR(255) DEFAULT NULL, quiz_id INT NOT NULL, INDEX IDX_C7782A28853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id)');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC6853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_AB6AFC6A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recommandation ADD CONSTRAINT FK_C7782A28853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E853CD175');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC6853CD175');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_AB6AFC6A76ED395');
        $this->addSql('ALTER TABLE recommandation DROP FOREIGN KEY FK_C7782A28853CD175');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE quiz');
        $this->addSql('DROP TABLE quiz_attempt');
        $this->addSql('DROP TABLE recommandation');
    }
}
