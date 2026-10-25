<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Shared stock columns. Each column is only added if missing, so that a shop which already
 * created them (same names and types) can adopt the plugin without a data migration.
 */
final class Version20261025000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shared stock: columns on sylius_product and sylius_product_variant.';
    }

    public function up(Schema $schema): void
    {
        $product = $schema->getTable('sylius_product');
        if (!$product->hasColumn('shared_stock_on_hand')) {
            $product->addColumn('shared_stock_on_hand', Types::INTEGER, ['notnull' => false]);
        }
        if (!$product->hasColumn('shared_stock_on_hold')) {
            $product->addColumn('shared_stock_on_hold', Types::INTEGER, ['default' => 0]);
        }
        if (!$product->hasColumn('shared_stock_unit')) {
            $product->addColumn('shared_stock_unit', Types::STRING, ['length' => 16, 'notnull' => false]);
        }
        if (!$product->hasColumn('shared_stock_version')) {
            $product->addColumn('shared_stock_version', Types::INTEGER, ['default' => 0]);
        }

        $variant = $schema->getTable('sylius_product_variant');
        if (!$variant->hasColumn('stock_consumption')) {
            $variant->addColumn('stock_consumption', Types::INTEGER, ['notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $product = $schema->getTable('sylius_product');
        foreach (['shared_stock_on_hand', 'shared_stock_on_hold', 'shared_stock_unit', 'shared_stock_version'] as $column) {
            if ($product->hasColumn($column)) {
                $product->dropColumn($column);
            }
        }
        $variant = $schema->getTable('sylius_product_variant');
        if ($variant->hasColumn('stock_consumption')) {
            $variant->dropColumn('stock_consumption');
        }
    }
}
