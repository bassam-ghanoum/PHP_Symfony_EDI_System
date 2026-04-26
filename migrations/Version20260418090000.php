<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates vendor, brand, product, item, buyer, incoming_file, invoice, and invoice_details tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS vendor (id INT AUTO_INCREMENT NOT NULL, internal_id VARCHAR(100) DEFAULT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS brand (id INT AUTO_INCREMENT NOT NULL, vendor_id INT NOT NULL, name VARCHAR(255) NOT NULL, INDEX IDX_1C52F958F603EE73 (vendor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS product (id INT AUTO_INCREMENT NOT NULL, brand_id INT NOT NULL, vendor_id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, product_code VARCHAR(100) DEFAULT NULL, INDEX IDX_D34A04ADF626D0FC (brand_id), INDEX IDX_D34A04ADF603EE73 (vendor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS item (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, brand_id INT NOT NULL, vendor_id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, ean VARCHAR(100) DEFAULT NULL, INDEX IDX_1F1B251E4584665A (product_id), INDEX IDX_1F1B251EF626D0FC (brand_id), INDEX IDX_1F1B251EF603EE73 (vendor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS buyer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, status VARCHAR(50) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS incoming_file (id INT AUTO_INCREMENT NOT NULL, file_name VARCHAR(255) DEFAULT NULL, file_status VARCHAR(50) DEFAULT NULL, received_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', file_type VARCHAR(50) DEFAULT NULL, original_file_name LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS orders (id INT AUTO_INCREMENT NOT NULL, brand_id INT NOT NULL, buyer_id INT DEFAULT NULL, file_id INT DEFAULT NULL, order_number VARCHAR(255) DEFAULT NULL, order_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', file_received_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', expected_delivery DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', season VARCHAR(100) DEFAULT NULL, status VARCHAR(50) DEFAULT NULL, INDEX IDX_E52FFDEEF626D0FC (brand_id), INDEX IDX_E52FFDEE1E59303B (buyer_id), INDEX IDX_E52FFDEEE934951A (file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS dispatch (id INT AUTO_INCREMENT NOT NULL, order_id INT DEFAULT NULL, file_id INT DEFAULT NULL, dispatcher_number VARCHAR(100) DEFAULT NULL, order_number VARCHAR(255) DEFAULT NULL, shipping_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', expected_arrival DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', file_received_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D3868542F7769A69 (order_id), INDEX IDX_D3868542E934951A (file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS invoice (id INT AUTO_INCREMENT NOT NULL, file_id INT DEFAULT NULL, invoice_number VARCHAR(255) DEFAULT NULL, order_id INT DEFAULT NULL, dispatch_id INT DEFAULT NULL, invoice_type VARCHAR(50) DEFAULT NULL, total_before_tax NUMERIC(12, 2) DEFAULT NULL, total_after_tax NUMERIC(12, 2) DEFAULT NULL, tax_type VARCHAR(50) DEFAULT NULL, tax_amount NUMERIC(12, 2) DEFAULT NULL, file_received_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9065174493CB796C (file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS invoice_details (id INT AUTO_INCREMENT NOT NULL, invoice_id INT DEFAULT NULL, item_id INT DEFAULT NULL, quantity INT DEFAULT NULL, unit_price NUMERIC(12, 2) DEFAULT NULL, total_before_tax NUMERIC(12, 2) DEFAULT NULL, total_after_tax NUMERIC(12, 2) DEFAULT NULL, INDEX IDX_63AA85F32989F1FD (invoice_id), INDEX IDX_63AA85F3126F525E (item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addConstraintIfNotExists('brand', 'FK_1C52F958F603EE73', 'ALTER TABLE brand ADD CONSTRAINT FK_1C52F958F603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id)');
        $this->addConstraintIfNotExists('product', 'FK_D34A04ADF626D0FC', 'ALTER TABLE product ADD CONSTRAINT FK_D34A04ADF626D0FC FOREIGN KEY (brand_id) REFERENCES brand (id)');
        $this->addConstraintIfNotExists('product', 'FK_D34A04ADF603EE73', 'ALTER TABLE product ADD CONSTRAINT FK_D34A04ADF603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id)');
        $this->addConstraintIfNotExists('item', 'FK_1F1B251E4584665A', 'ALTER TABLE item ADD CONSTRAINT FK_1F1B251E4584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addConstraintIfNotExists('item', 'FK_1F1B251EF626D0FC', 'ALTER TABLE item ADD CONSTRAINT FK_1F1B251EF626D0FC FOREIGN KEY (brand_id) REFERENCES brand (id)');
        $this->addConstraintIfNotExists('item', 'FK_1F1B251EF603EE73', 'ALTER TABLE item ADD CONSTRAINT FK_1F1B251EF603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id)');
        $this->addConstraintIfNotExists('orders', 'FK_E52FFDEE1E59303B', 'ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE1E59303B FOREIGN KEY (buyer_id) REFERENCES buyer (id) ON DELETE SET NULL');
        $this->addConstraintIfNotExists('orders', 'FK_E52FFDEEF626D0FC', 'ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEF626D0FC FOREIGN KEY (brand_id) REFERENCES brand (id)');
        $this->addConstraintIfNotExists('orders', 'FK_E52FFDEEE934951A', 'ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEE934951A FOREIGN KEY (file_id) REFERENCES incoming_file (id) ON DELETE SET NULL');
        $this->addConstraintIfNotExists('dispatch', 'FK_D3868542F7769A69', 'ALTER TABLE dispatch ADD CONSTRAINT FK_D3868542F7769A69 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL');
        $this->addConstraintIfNotExists('dispatch', 'FK_D3868542E934951A', 'ALTER TABLE dispatch ADD CONSTRAINT FK_D3868542E934951A FOREIGN KEY (file_id) REFERENCES incoming_file (id) ON DELETE SET NULL');
        $this->addConstraintIfNotExists('invoice', 'FK_9065174493CB796C', 'ALTER TABLE invoice ADD CONSTRAINT FK_9065174493CB796C FOREIGN KEY (file_id) REFERENCES incoming_file (id) ON DELETE SET NULL');
        $this->addConstraintIfNotExists('invoice_details', 'FK_63AA85F32989F1FD', 'ALTER TABLE invoice_details ADD CONSTRAINT FK_63AA85F32989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) ON DELETE SET NULL');
        $this->addConstraintIfNotExists('invoice_details', 'FK_63AA85F3126F525E', 'ALTER TABLE invoice_details ADD CONSTRAINT FK_63AA85F3126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addConstraintIfNotExists('invoice', 'FK_INVOICE_ORDER_ID', 'ALTER TABLE invoice ADD CONSTRAINT FK_INVOICE_ORDER_ID FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL');
        $this->addConstraintIfNotExists('invoice', 'FK_INVOICE_DISPATCH_ID', 'ALTER TABLE invoice ADD CONSTRAINT FK_INVOICE_DISPATCH_ID FOREIGN KEY (dispatch_id) REFERENCES dispatch (id) ON DELETE SET NULL');
    }

    private function addConstraintIfNotExists(string $tableName, string $constraintName, string $sql): void
    {
        $result = $this->connection->executeQuery(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?",
            [$constraintName]
        );

        if ($result->rowCount() === 0) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist(['invoice'])) {
            $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_INVOICE_ORDER_ID');
            $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_INVOICE_DISPATCH_ID');
        }

        if ($schemaManager->tablesExist(['dispatch'])) {
            $this->addSql('ALTER TABLE dispatch DROP FOREIGN KEY FK_D3868542F7769A69');
            $this->addSql('ALTER TABLE dispatch DROP FOREIGN KEY FK_D3868542E934951A');
        }

        if ($schemaManager->tablesExist(['orders'])) {
            $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE1E59303B');
            $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEF626D0FC');
            $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEE934951A');
        }

        $this->addSql('ALTER TABLE invoice_details DROP FOREIGN KEY FK_63AA85F32989F1FD');
        $this->addSql('ALTER TABLE invoice_details DROP FOREIGN KEY FK_63AA85F3126F525E');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_9065174493CB796C');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251E4584665A');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251EF626D0FC');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251EF603EE73');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADF626D0FC');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADF603EE73');
        $this->addSql('ALTER TABLE brand DROP FOREIGN KEY FK_1C52F958F603EE73');
        $this->addSql('DROP TABLE dispatch');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE invoice_details');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE incoming_file');
        $this->addSql('DROP TABLE buyer');
        $this->addSql('DROP TABLE item');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE brand');
        $this->addSql('DROP TABLE vendor');
    }
}
