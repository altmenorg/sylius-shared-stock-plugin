<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Grid;

use Sylius\Component\Grid\Definition\Field;
use Sylius\Component\Grid\Event\GridDefinitionConverterEvent;

/**
 * Admin lists: a "Shared stock" column in the product grid, and the variant grid's inventory
 * column made aware of shared stock (consumption and units available instead of "not tracked").
 * Done on the grid definition events, so that the application's own grid configuration is
 * left as it is.
 */
final class AdminGridListener
{
    private const TEMPLATES = '@AltmenorgSyliusSharedStockPlugin/admin';

    /** `sylius.grid.admin_product` */
    public function onProductGrid(GridDefinitionConverterEvent $event): void
    {
        $grid = $event->getGrid();
        if ($grid->hasField('shared_stock')) {
            return;
        }
        $field = Field::fromNameAndType('shared_stock', 'twig');
        $field->setPath('.');
        $field->setLabel('altmenorg_sylius_shared_stock.ui.shared_stock');
        $field->setOptions([
            'template' => self::TEMPLATES . '/product/grid/field/shared_stock.html.twig',
            'vars' => ['th_class' => 'text-center'],
        ]);
        // right before "enabled", or last. Fields are shown by position, then in definition order
        // (most share the default position): number them all again to place the new one.
        $fields = $grid->getFields();
        uasort($fields, static fn (Field $a, Field $b): int => $a->getPosition() <=> $b->getPosition());
        $position = 0;
        foreach ($fields as $name => $other) {
            if ($name === 'enabled') {
                $field->setPosition($position++);
            }
            $other->setPosition($position++);
        }
        if (!$grid->hasField('enabled')) {
            $field->setPosition($position);
        }
        $grid->addField($field);
    }

    /** `sylius.grid.admin_product_variant` */
    public function onProductVariantGrid(GridDefinitionConverterEvent $event): void
    {
        $grid = $event->getGrid();
        if (!$grid->hasField('inventory')) {
            return;
        }
        $field = $grid->getField('inventory');
        $options = $field->getOptions();
        $options['vars'] = ($options['vars'] ?? []) + ['shared_stock_fallback' => $options['template'] ?? null];
        $options['template'] = self::TEMPLATES . '/product_variant/grid/field/inventory.html.twig';
        $field->setOptions($options);
    }
}
