<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Tests\Unit;

use Altmenorg\SyliusSharedStockPlugin\Admin\PendingSharedStockChanges;
use Altmenorg\SyliusSharedStockPlugin\Admin\SharedStockChange;
use Altmenorg\SyliusSharedStockPlugin\Grid\AdminGridListener;
use Altmenorg\SyliusSharedStockPlugin\Tests\Fixtures\Product;
use Altmenorg\SyliusSharedStockPlugin\Tests\Fixtures\ProductVariant;
use Altmenorg\SyliusSharedStockPlugin\Twig\SharedStockExtension;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Grid\Definition\Field;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Event\GridDefinitionConverterEvent;

final class AdminHelpersTest extends TestCase
{
    public function testTwigHelpers(): void
    {
        $twig = new SharedStockExtension();
        $product = new Product(1);
        $variant = ProductVariant::of($product, 250);
        self::assertNull($twig->product($variant));
        self::assertNull($twig->variantUnits($variant));

        $product->setSharedStockOnHand(1000);
        $product->setSharedStockOnHold(200);
        self::assertSame($product, $twig->product($variant));
        self::assertSame(800, $twig->free($product));
        self::assertSame(3, $twig->variantUnits($variant));
        self::assertNull($twig->variantUnits(ProductVariant::of($product, null)));

        $product->setSharedStockOnHand(-13);
        $product->setSharedStockOnHold(0);
        self::assertSame(0, $twig->variantUnits($variant));
    }

    public function testCorrectionIsADifference(): void
    {
        self::assertSame(50, SharedStockChange::correct(600, 650)->delta());
        self::assertSame(-600, SharedStockChange::correct(600, 0)->delta());
    }

    public function testPendingChangesAreTakenOnce(): void
    {
        $pending = new PendingSharedStockChanges();
        $product = new Product(1);
        $pending->set($product, SharedStockChange::enable(10));
        self::assertSame(SharedStockChange::ENABLE, $pending->take($product)?->type);
        self::assertNull($pending->take($product));

        $pending->set($product, SharedStockChange::disable());
        $pending->reset();
        self::assertNull($pending->take($product));
    }

    private function grid(string ...$fields): Grid
    {
        $grid = Grid::fromCodeAndDriverConfiguration('sylius_admin_product', 'doctrine/orm', []);
        foreach ($fields as $position => $name) {
            $field = Field::fromNameAndType($name, 'twig');
            $field->setOptions(['template' => "@SyliusAdmin/$name.html.twig"]);
            $field->setPosition($position);
            $grid->addField($field);
        }

        return $grid;
    }

    public function testProductGridColumnBeforeEnabled(): void
    {
        $grid = $this->grid('image', 'name', 'code', 'enabled');
        (new AdminGridListener())->onProductGrid(new GridDefinitionConverterEvent($grid));

        $positions = array_map(static fn (Field $f): int => $f->getPosition(), $grid->getFields());
        asort($positions);
        self::assertSame(['image', 'name', 'code', 'shared_stock', 'enabled'], array_keys($positions));
    }

    public function testVariantGridKeepsTheOriginalTemplateAsFallback(): void
    {
        $grid = $this->grid('name', 'inventory');
        (new AdminGridListener())->onProductVariantGrid(new GridDefinitionConverterEvent($grid));

        $options = $grid->getField('inventory')->getOptions();
        self::assertSame('@AltmenorgSyliusSharedStockPlugin/admin/product_variant/grid/field/inventory.html.twig', $options['template']);
        self::assertSame('@SyliusAdmin/inventory.html.twig', $options['vars']['shared_stock_fallback']);
    }
}
