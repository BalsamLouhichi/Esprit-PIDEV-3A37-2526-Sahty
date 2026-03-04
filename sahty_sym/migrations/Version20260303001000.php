<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by and updated_by columns to resultat_analyse.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resultat_analyse ADD COLUMN IF NOT EXISTS created_by VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE resultat_analyse ADD COLUMN IF NOT EXISTS updated_by VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resultat_analyse DROP COLUMN IF EXISTS created_by');
        $this->addSql('ALTER TABLE resultat_analyse DROP COLUMN IF EXISTS updated_by');
    }
}

