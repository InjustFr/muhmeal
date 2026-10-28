<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028111006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE meal (id UUID NOT NULL, eaten_on DATE NOT NULL, type VARCHAR(16) NOT NULL, title VARCHAR(80) DEFAULT NULL, logged_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, logged_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9EF68E9CE5FF5387 ON meal (eaten_on)');
        $this->addSql('CREATE INDEX IDX_9EF68E9CE877DD6E ON meal (logged_by_id)');
        $this->addSql('CREATE TABLE meal_eater (meal_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (meal_id, user_id))');
        $this->addSql('CREATE INDEX IDX_5BD938EE639666D6 ON meal_eater (meal_id)');
        $this->addSql('CREATE INDEX IDX_5BD938EEA76ED395 ON meal_eater (user_id)');
        $this->addSql('CREATE TABLE meal_line (id UUID NOT NULL, name VARCHAR(60) NOT NULL, unit VARCHAR(16) NOT NULL, position INT NOT NULL, used_twelfths INT NOT NULL, meal_id UUID NOT NULL, ingredient_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_CBCA6702639666D6 ON meal_line (meal_id)');
        $this->addSql('CREATE INDEX IDX_CBCA6702933FE08C ON meal_line (ingredient_id)');
        $this->addSql('CREATE TABLE recipe (id UUID NOT NULL, name VARCHAR(80) NOT NULL, yield_twelfths INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, output_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DA88B137DE097880 ON recipe (output_id)');
        $this->addSql('CREATE TABLE recipe_line (id UUID NOT NULL, amount_twelfths INT DEFAULT NULL, position INT NOT NULL, recipe_id UUID NOT NULL, ingredient_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AE0FEE2959D8A214933FE08C ON recipe_line (recipe_id, ingredient_id)');
        $this->addSql('CREATE INDEX IDX_AE0FEE2959D8A214 ON recipe_line (recipe_id)');
        $this->addSql('CREATE INDEX IDX_AE0FEE29933FE08C ON recipe_line (ingredient_id)');
        $this->addSql('ALTER TABLE meal ADD CONSTRAINT FK_9EF68E9CE877DD6E FOREIGN KEY (logged_by_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE meal_eater ADD CONSTRAINT FK_5BD938EE639666D6 FOREIGN KEY (meal_id) REFERENCES meal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meal_eater ADD CONSTRAINT FK_5BD938EEA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meal_line ADD CONSTRAINT FK_CBCA6702639666D6 FOREIGN KEY (meal_id) REFERENCES meal (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE meal_line ADD CONSTRAINT FK_CBCA6702933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredient (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B137DE097880 FOREIGN KEY (output_id) REFERENCES ingredient (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE recipe_line ADD CONSTRAINT FK_AE0FEE2959D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE recipe_line ADD CONSTRAINT FK_AE0FEE29933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredient (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meal DROP CONSTRAINT FK_9EF68E9CE877DD6E');
        $this->addSql('ALTER TABLE meal_eater DROP CONSTRAINT FK_5BD938EE639666D6');
        $this->addSql('ALTER TABLE meal_eater DROP CONSTRAINT FK_5BD938EEA76ED395');
        $this->addSql('ALTER TABLE meal_line DROP CONSTRAINT FK_CBCA6702639666D6');
        $this->addSql('ALTER TABLE meal_line DROP CONSTRAINT FK_CBCA6702933FE08C');
        $this->addSql('ALTER TABLE recipe DROP CONSTRAINT FK_DA88B137DE097880');
        $this->addSql('ALTER TABLE recipe_line DROP CONSTRAINT FK_AE0FEE2959D8A214');
        $this->addSql('ALTER TABLE recipe_line DROP CONSTRAINT FK_AE0FEE29933FE08C');
        $this->addSql('DROP TABLE meal');
        $this->addSql('DROP TABLE meal_eater');
        $this->addSql('DROP TABLE meal_line');
        $this->addSql('DROP TABLE recipe');
        $this->addSql('DROP TABLE recipe_line');
    }
}
