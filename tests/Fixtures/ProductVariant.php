<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Tests\Fixtures;

use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockVariantInterface;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockVariantTrait;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;

class ProductVariant extends BaseProductVariant implements SharedStockVariantInterface
{
    use SharedStockVariantTrait;

    public static function of(Product $product, ?int $consumption, string $code = 'V'): self
    {
        $variant = new self();
        $variant->setCode($code);
        $variant->setStockConsumption($consumption);
        $product->addVariant($variant);

        return $variant;
    }
}
