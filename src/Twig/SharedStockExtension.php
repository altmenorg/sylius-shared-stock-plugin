<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Twig;

use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockVariantInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Read-only helpers for templates; safe to call on products and variants without shared stock. */
final class SharedStockExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_shared_stock_product', $this->product(...)),
            new TwigFunction('sylius_shared_stock_free', $this->free(...)),
            new TwigFunction('sylius_shared_stock_variant_units', $this->variantUnits(...)),
        ];
    }

    /** The product if it uses a shared stock, else null. Accepts a product or a variant. */
    public function product(mixed $subject): ?SharedStockProductInterface
    {
        if ($subject instanceof ProductVariantInterface) {
            $subject = $subject->getProduct();
        }

        return $subject instanceof SharedStockProductInterface && $subject->isSharedStockEnabled() ? $subject : null;
    }

    /** Base units free to sell (on hand minus on hold), or null without shared stock. */
    public function free(mixed $subject): ?int
    {
        $product = $this->product($subject);

        return $product === null ? null : (int) $product->getSharedStockOnHand() - $product->getSharedStockOnHold();
    }

    /** Units of this variant that can still be sold from the shared stock, or null if it does not draw on one. */
    public function variantUnits(mixed $variant): ?int
    {
        if (!$variant instanceof SharedStockVariantInterface || !$variant->getStockConsumption()) {
            return null;
        }
        $free = $this->free($variant);

        return $free === null ? null : intdiv(max(0, $free), $variant->getStockConsumption());
    }
}
