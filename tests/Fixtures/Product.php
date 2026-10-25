<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Tests\Fixtures;

use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductTrait;
use Sylius\Component\Core\Model\Product as BaseProduct;

class Product extends BaseProduct implements SharedStockProductInterface
{
    use SharedStockProductTrait;

    public function __construct(?int $id = null)
    {
        parent::__construct();
        $this->id = $id;
    }
}
