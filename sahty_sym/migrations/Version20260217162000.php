<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs de paiement (BTCPay) sur la table commande';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE commande ADD mode_paiement VARCHAR(30) DEFAULT 'cash_on_delivery' NOT NULL, ADD payment_status VARCHAR(30) DEFAULT 'not_required' NOT NULL, ADD payment_provider VARCHAR(50) DEFAULT NULL, ADD payment_reference VARCHAR(255) DEFAULT NULL, ADD payment_url LONGTEXT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP mode_paiement, DROP payment_status, DROP payment_provider, DROP payment_reference, DROP payment_url');
    }
}
