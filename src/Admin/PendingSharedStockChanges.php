<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Admin;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Changes asked for in the admin product form, kept until the product is saved.
 *
 * The form only records them: Sylius' admin forms are live components, submitted again at every
 * re-render, so moving the stock from a form listener would move it on every keystroke. The
 * change is applied by AdminSharedStockListener on `sylius.product.post_create` / `post_update`,
 * which only the real save dispatches.
 */
final class PendingSharedStockChanges implements ResetInterface
{
    /** @var array<int, SharedStockChange> */
    private array $changes = [];

    public function set(object $product, SharedStockChange $change): void
    {
        $this->changes[spl_object_id($product)] = $change;
    }

    public function forget(object $product): void
    {
        unset($this->changes[spl_object_id($product)]);
    }

    public function take(object $product): ?SharedStockChange
    {
        $change = $this->changes[spl_object_id($product)] ?? null;
        $this->forget($product);

        return $change;
    }

    public function reset(): void
    {
        $this->changes = [];
    }
}
