<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Tests\Unit;

use Altmenorg\SyliusSharedStockPlugin\Inventory\SharedStockAvailabilityChecker;
use Altmenorg\SyliusSharedStockPlugin\Tests\Fixtures\Product;
use Altmenorg\SyliusSharedStockPlugin\Tests\Fixtures\ProductVariant;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;

final class SharedStockAvailabilityCheckerTest extends TestCase
{
    private function product(?int $onHand, int $onHold = 0): Product
    {
        $product = new Product(1);
        $product->setSharedStockOnHand($onHand);
        $product->setSharedStockOnHold($onHold);

        return $product;
    }

    private function checker(bool $allowNegative = false, ?bool $inner = null): SharedStockAvailabilityChecker
    {
        $decorated = $this->createMock(AvailabilityCheckerInterface::class);
        if ($inner === null) {
            $decorated->expects(self::never())->method('isStockSufficient');
        } else {
            $decorated->expects(self::once())->method('isStockSufficient')->willReturn($inner);
        }

        return new SharedStockAvailabilityChecker($decorated, $allowNegative);
    }

    public function testFreeStockDividedByConsumption(): void
    {
        $product = $this->product(380);
        self::assertTrue($this->checker()->isStockAvailable(ProductVariant::of($product, 100)));
        self::assertTrue($this->checker()->isStockAvailable(ProductVariant::of($product, 250)));
        self::assertFalse($this->checker()->isStockAvailable(ProductVariant::of($product, 500)));
        self::assertTrue($this->checker()->isStockSufficient(ProductVariant::of($product, 100), 3));
        self::assertFalse($this->checker()->isStockSufficient(ProductVariant::of($product, 100), 4));
    }

    public function testReservedStockIsNotFree(): void
    {
        $product = $this->product(380, 300);
        self::assertFalse($this->checker()->isStockAvailable(ProductVariant::of($product, 100)));
        self::assertTrue($this->checker()->isStockAvailable(ProductVariant::of($product, 80)));
    }

    public function testAllowNegativeAlwaysAvailable(): void
    {
        self::assertTrue($this->checker(true)->isStockSufficient(ProductVariant::of($this->product(0), 100), 50));
    }

    public function testWithoutSharedStockSyliusDecides(): void
    {
        self::assertFalse($this->checker(false, false)->isStockAvailable(ProductVariant::of($this->product(null), 100)));
    }

    public function testVariantWithoutConsumptionKeepsItsOwnStock(): void
    {
        self::assertTrue($this->checker(false, true)->isStockAvailable(ProductVariant::of($this->product(0), null)));
    }
}
