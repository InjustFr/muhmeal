<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028114627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE grocery_item (id UUID NOT NULL, ticked BOOLEAN NOT NULL, list_id UUID NOT NULL, ingredient_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8F9EDB8A3DAE168B933FE08C ON grocery_item (list_id, ingredient_id)');
        $this->addSql('CREATE INDEX IDX_8F9EDB8A3DAE168B ON grocery_item (list_id)');
        $this->addSql('CREATE INDEX IDX_8F9EDB8A933FE08C ON grocery_item (ingredient_id)');
        $this->addSql('CREATE TABLE grocery_list (id UUID NOT NULL, open BOOLEAN NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D44D068CA47083A4 ON grocery_list (open)');
        $this->addSql('CREATE INDEX IDX_D44D068C9740C9D5 ON grocery_list (started_by_id)');
        $this->addSql('ALTER TABLE grocery_item ADD CONSTRAINT FK_8F9EDB8A3DAE168B FOREIGN KEY (list_id) REFERENCES grocery_list (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE grocery_item ADD CONSTRAINT FK_8F9EDB8A933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredient (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE grocery_list ADD CONSTRAINT FK_D44D068C9740C9D5 FOREIGN KEY (started_by_id) REFERENCES app_user (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE grocery_item DROP CONSTRAINT FK_8F9EDB8A3DAE168B');
        $this->addSql('ALTER TABLE grocery_item DROP CONSTRAINT FK_8F9EDB8A933FE08C');
        $this->addSql('ALTER TABLE grocery_list DROP CONSTRAINT FK_D44D068C9740C9D5');
        $this->addSql('DROP TABLE grocery_item');
        $this->addSql('DROP TABLE grocery_list');
    }
}
