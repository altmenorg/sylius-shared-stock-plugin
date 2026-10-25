<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait SharedStockVariantTrait
{
    #[ORM\Column(name: 'stock_consumption', type: 'integer', nullable: true)]
    protected ?int $stockConsumption = null;

    public function getStockConsumption(): ?int
    {
        return $this->stockConsumption;
    }

    public function setStockConsumption(?int $consumption): void
    {
        $this->stockConsumption = $consumption;
    }
}
