<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Inventory;

use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockVariantInterface;
use Sylius\Component\Core\Inventory\Operator\OrderInventoryOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentStates;

/**
 * Hold, sell and cancel for variants that draw on a shared stock; the rest is left to Sylius.
 * Shared-stock variants are untracked for Sylius (tracked = false), so the decorated operator
 * skips them. Quantities are summed per product before moving the stock: one order with
 * 2 × 100 g and 1 × 250 g of the same product is one atomic hold of 450 g.
 *
 * Decorates `sylius.operator.inventory.order_inventory` (priority -10).
 */
final class SharedStockOrderInventoryOperator implements OrderInventoryOperatorInterface
{
    public function __construct(
        private readonly OrderInventoryOperatorInterface $inner,
        private readonly SharedStockStoreInterface $store,
    ) {
    }

    public function hold(OrderInterface $order): void
    {
        $held = [];
        try {
            foreach ($this->demand($order) as [$product, $quantity]) {
                $this->store->hold($product, $quantity);
                $held[] = [$product, $quantity];
            }
        } catch (\Throwable $e) {
            // all or nothing: what was already held for this order is released
            foreach ($held as [$product, $quantity]) {
                $this->store->release($product, $quantity);
            }

            throw $e;
        }
        $this->inner->hold($order);
    }

    public function sell(OrderInterface $order): void
    {
        foreach ($this->demand($order) as [$product, $quantity]) {
            $this->store->sell($product, $quantity);
        }
        $this->inner->sell($order);
    }

    public function cancel(OrderInterface $order): void
    {
        $paid = in_array($order->getPaymentState(), [OrderPaymentStates::STATE_PAID, OrderPaymentStates::STATE_REFUNDED], true);
        foreach ($this->demand($order) as [$product, $quantity]) {
            $paid ? $this->store->giveBack($product, $quantity) : $this->store->release($product, $quantity);
        }
        $this->inner->cancel($order);
    }

    /** @return list<array{0: SharedStockProductInterface, 1: int}> base units per shared product */
    private function demand(OrderInterface $order): array
    {
        $byProduct = [];
        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();
            $product = $variant?->getProduct();
            if (!$variant instanceof SharedStockVariantInterface || !$product instanceof SharedStockProductInterface
                || !$product->isSharedStockEnabled() || !$variant->getStockConsumption()) {
                continue;
            }
            $key = spl_object_id($product);
            $byProduct[$key] ??= [$product, 0];
            $byProduct[$key][1] += $item->getQuantity() * $variant->getStockConsumption();
        }

        return array_values($byProduct);
    }
}
