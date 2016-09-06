<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160901113345 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql(file_get_contents(__DIR__ . '/../dump/hunkemoller_product.sql'));
        $this->addSql(file_get_contents(__DIR__ . '/../dump/hunkemoller_product_image.sql'));
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->addSql('TRUNCATE TABLE product');
        $this->addSql('TRUNCATE TABLE product_image');
    }
}
