<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Columns of the shared stock on the product. Stock movements never go through these setters
 * during checkout: they are atomic SQL updates (SharedStockStore), the entity is only refreshed.
 */
trait SharedStockProductTrait
{
    #[ORM\Column(name: 'shared_stock_on_hand', type: 'integer', nullable: true)]
    protected ?int $sharedStockOnHand = null;

    #[ORM\Column(name: 'shared_stock_on_hold', type: 'integer', options: ['default' => 0])]
    protected int $sharedStockOnHold = 0;

    /**
     * Bumped by every movement: MySQL/MariaDB report 0 affected rows for an UPDATE that changes
     * nothing (replace by the same value), which would read as a refusal.
     */
    #[ORM\Column(name: 'shared_stock_version', type: 'integer', options: ['default' => 0])]
    protected int $sharedStockVersion = 0;

    #[ORM\Column(name: 'shared_stock_unit', type: 'string', length: 16, nullable: true)]
    protected ?string $sharedStockUnit = null;

    public function getSharedStockOnHand(): ?int
    {
        return $this->sharedStockOnHand;
    }

    /** For imports and fixtures only: on a live product, move the stock with SharedStockStore. */
    public function setSharedStockOnHand(?int $onHand): void
    {
        $this->sharedStockOnHand = $onHand;
    }

    public function getSharedStockOnHold(): int
    {
        return $this->sharedStockOnHold;
    }

    /** For imports and fixtures only: on a live product, move the stock with SharedStockStore. */
    public function setSharedStockOnHold(int $onHold): void
    {
        $this->sharedStockOnHold = $onHold;
    }

    public function getSharedStockUnit(): ?string
    {
        return $this->sharedStockUnit;
    }

    public function setSharedStockUnit(?string $unit): void
    {
        $this->sharedStockUnit = $unit;
    }

    public function isSharedStockEnabled(): bool
    {
        return $this->sharedStockOnHand !== null;
    }
}
