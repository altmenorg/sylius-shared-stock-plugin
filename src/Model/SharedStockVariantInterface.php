<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Model;

/** A variant drawing on its product's shared stock: how many base units one unit sold consumes. */
interface SharedStockVariantInterface
{
    public function getStockConsumption(): ?int;

    public function setStockConsumption(?int $consumption): void;
}
