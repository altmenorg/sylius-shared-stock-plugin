<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Model;

/**
 * A product whose variants draw on one stock, counted in a base unit (grams, pieces, metres…).
 * Shared stock is enabled when getSharedStockOnHand() is not null.
 */
interface SharedStockProductInterface
{
    /** @return mixed the product identifier (Sylius ResourceInterface::getId()) */
    public function getId();

    public function getSharedStockOnHand(): ?int;

    public function getSharedStockOnHold(): int;

    public function getSharedStockUnit(): ?string;

    public function setSharedStockUnit(?string $unit): void;

    public function isSharedStockEnabled(): bool;
}
