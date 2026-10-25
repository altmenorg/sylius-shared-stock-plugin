<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\EventListener;

use Altmenorg\SyliusSharedStockPlugin\Admin\PendingSharedStockChanges;
use Altmenorg\SyliusSharedStockPlugin\Admin\SharedStockChange;
use Altmenorg\SyliusSharedStockPlugin\Exception\InsufficientSharedStockException;
use Altmenorg\SyliusSharedStockPlugin\Inventory\SharedStockStoreInterface;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Applies the shared stock change asked for in the admin product form, once the product is saved
 * (`sylius.product.post_create`, `sylius.product.post_update`), through the same checked UPDATE
 * as the shop's movements. A correction is applied as a difference (+50, -120) to the stock as
 * it is now: a sale made while the form was open is kept. A refusal leaves the stock unchanged
 * and is reported to the admin.
 */
final class AdminSharedStockListener
{
    public function __construct(
        private readonly PendingSharedStockChanges $pending,
        private readonly SharedStockStoreInterface $store,
        private readonly RequestStack $requestStack,
        private readonly bool $allowNegative = false,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $product = $event->getSubject();
        if (!$product instanceof SharedStockProductInterface) {
            return;
        }
        $change = $this->pending->take($product);
        if ($change === null) {
            return;
        }
        $unit = (string) $product->getSharedStockUnit();
        try {
            switch ($change->type) {
                case SharedStockChange::ENABLE:
                    $this->store->enable($product, (int) $change->to);
                    break;
                case SharedStockChange::DISABLE:
                    $this->store->disable($product);
                    break;
                case SharedStockChange::CORRECT:
                    $delta = $change->delta();
                    $delta >= 0
                        ? $this->store->adjust($product, 'increase', $delta)
                        : $this->store->adjust($product, 'decrease', -$delta, $this->allowNegative);
                    $now = (int) $product->getSharedStockOnHand();
                    if ($now !== $change->to) {
                        $this->flash('info', 'altmenorg_sylius_shared_stock.flash.changed_meanwhile', [
                            '%from%' => $change->from,
                            '%before%' => $now - $delta,
                            '%delta%' => sprintf('%+d', $delta),
                            '%now%' => $now,
                            '%unit%' => $unit,
                        ]);
                    }
                    break;
            }
        } catch (InsufficientSharedStockException) {
            $this->flash('error', 'altmenorg_sylius_shared_stock.flash.refused_' . $change->type, [
                '%on_hand%' => (int) $product->getSharedStockOnHand(),
                '%on_hold%' => $product->getSharedStockOnHold(),
                '%delta%' => $change->type === SharedStockChange::CORRECT ? sprintf('%+d', $change->delta()) : '',
                '%unit%' => $unit,
            ]);
        }
    }

    private function flash(string $type, string $message, array $parameters): void
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (\Throwable) {
            return;
        }
        if ($session instanceof FlashBagAwareSessionInterface) {
            // translated by the admin's flash template (domain "flashes")
            $session->getFlashBag()->add($type, ['message' => $message, 'parameters' => $parameters]);
        }
    }
}
