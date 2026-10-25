<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Inventory;

use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockVariantInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Inventory\Model\StockableInterface;

/**
 * "In stock / out of stock" of a shared-stock variant: free base units divided by what one unit
 * consumes (380 g free: the 100 g bag is available, the 500 g bag is not). Used by the shop
 * (`sylius_inventory_is_available`), the cart and the checkout validators.
 *
 * Decorates `sylius.checker.inventory.availability`.
 */
final class SharedStockAvailabilityChecker implements AvailabilityCheckerInterface
{
    public function __construct(
        private readonly AvailabilityCheckerInterface $inner,
        private readonly bool $allowNegative = false,
    ) {
    }

    public function isStockAvailable(StockableInterface $stockable): bool
    {
        return $this->isStockSufficient($stockable, 1);
    }

    public function isStockSufficient(StockableInterface $stockable, int $quantity): bool
    {
        $product = $stockable instanceof ProductVariantInterface ? $stockable->getProduct() : null;
        if (!$stockable instanceof SharedStockVariantInterface || !$product instanceof SharedStockProductInterface
            || !$product->isSharedStockEnabled() || !$stockable->getStockConsumption()) {
            return $this->inner->isStockSufficient($stockable, $quantity);
        }

        return $this->allowNegative
            || $quantity * $stockable->getStockConsumption() <= $product->getSharedStockOnHand() - $product->getSharedStockOnHold();
    }
}
