<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Inventory;

use Altmenorg\SyliusSharedStockPlugin\Exception\InsufficientSharedStockException;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;

/**
 * Moves of a shared stock. Every method is one checked, atomic database update: a refused
 * movement throws InsufficientSharedStockException and changes nothing.
 */
interface SharedStockStoreInterface
{
    /** Reserves stock for an order awaiting payment. */
    public function hold(SharedStockProductInterface $product, int $quantity): void;

    /** Gives back a reservation (payment failed, order cancelled before payment). */
    public function release(SharedStockProductInterface $product, int $quantity): void;

    /** Turns a reservation into a sale. */
    public function sell(SharedStockProductInterface $product, int $quantity): void;

    /** Puts sold stock back (paid order cancelled or refunded). */
    public function giveBack(SharedStockProductInterface $product, int $quantity): void;

    /**
     * Direct movement from a back office or an external system (inventory, delivery, correction):
     * `replace`, `increase` or `decrease`.
     *
     * @throws InsufficientSharedStockException
     */
    public function adjust(SharedStockProductInterface $product, string $method, int $quantity, bool $allowNegative = false): void;

    /** Turns the shared stock on, with this stock on hand; refused if it is already on. */
    public function enable(SharedStockProductInterface $product, int $onHand): void;

    /** Turns the shared stock off; refused while part of it is reserved by orders. */
    public function disable(SharedStockProductInterface $product): void;

    /** Units of base unit free to sell (on hand minus on hold), read from the database. */
    public function available(SharedStockProductInterface $product): int;
}
