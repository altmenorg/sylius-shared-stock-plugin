<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Form\Extension;

use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockVariantInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantType;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * Stock consumption of a variant: base units of the product's shared stock taken by one unit sold.
 *
 * A variant that draws on a shared stock is untracked for Sylius: if it were tracked as well,
 * one sale would move both stocks. Saving such a variant therefore sets `tracked` to false.
 */
final class ProductVariantTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ProductVariantType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!is_a((string) ($options['data_class'] ?? ''), SharedStockVariantInterface::class, true)) {
            return;
        }
        $builder->add('stockConsumption', IntegerType::class, [
            'required' => false,
            'label' => 'altmenorg_sylius_shared_stock.form.variant.consumption',
            'help' => 'altmenorg_sylius_shared_stock.form.variant.consumption_help',
            'constraints' => [new Positive(groups: ['sylius', 'Default'])],
        ]);
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $variant = $event->getData();
            if (!$variant instanceof SharedStockVariantInterface || !$variant instanceof ProductVariantInterface
                || !$variant->getStockConsumption()) {
                return;
            }
            $product = $variant->getProduct();
            if ($product instanceof SharedStockProductInterface && $product->isSharedStockEnabled()) {
                $variant->setTracked(false);
            }
        });
    }
}
