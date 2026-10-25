<?php

declare(strict_types=1);

use Altmenorg\SyliusSharedStockPlugin\Admin\PendingSharedStockChanges;
use Altmenorg\SyliusSharedStockPlugin\EventListener\AdminSharedStockListener;
use Altmenorg\SyliusSharedStockPlugin\Form\Extension\ProductTypeExtension;
use Altmenorg\SyliusSharedStockPlugin\Form\Extension\ProductVariantTypeExtension;
use Altmenorg\SyliusSharedStockPlugin\Grid\AdminGridListener;
use Altmenorg\SyliusSharedStockPlugin\Inventory\SharedStockAvailabilityChecker;
use Altmenorg\SyliusSharedStockPlugin\Inventory\SharedStockOrderInventoryOperator;
use Altmenorg\SyliusSharedStockPlugin\Inventory\SharedStockStore;
use Altmenorg\SyliusSharedStockPlugin\Inventory\SharedStockStoreInterface;
use Altmenorg\SyliusSharedStockPlugin\Twig\SharedStockExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->private();
    $allowNegative = param('altmenorg_sylius_shared_stock.allow_negative');

    $services->set('altmenorg_sylius_shared_stock.store', SharedStockStore::class)
        ->args([service('doctrine.orm.entity_manager'), $allowNegative]);
    $services->alias(SharedStockStoreInterface::class, 'altmenorg_sylius_shared_stock.store');
    $services->alias(SharedStockStore::class, 'altmenorg_sylius_shared_stock.store');

    $services->set('altmenorg_sylius_shared_stock.checker.availability', SharedStockAvailabilityChecker::class)
        ->decorate('sylius.checker.inventory.availability')
        ->args([service('.inner'), $allowNegative]);

    $services->set('altmenorg_sylius_shared_stock.operator.order_inventory', SharedStockOrderInventoryOperator::class)
        ->decorate('sylius.operator.inventory.order_inventory', null, -10)
        ->args([service('.inner'), service('altmenorg_sylius_shared_stock.store')]);

    // admin
    $services->set('altmenorg_sylius_shared_stock.admin.pending_changes', PendingSharedStockChanges::class)
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('altmenorg_sylius_shared_stock.form.extension.product', ProductTypeExtension::class)
        ->args([service('altmenorg_sylius_shared_stock.admin.pending_changes'), service('translator'), $allowNegative])
        ->tag('form.type_extension');

    $services->set('altmenorg_sylius_shared_stock.form.extension.product_variant', ProductVariantTypeExtension::class)
        ->tag('form.type_extension');

    $services->set('altmenorg_sylius_shared_stock.listener.admin_product', AdminSharedStockListener::class)
        ->args([
            service('altmenorg_sylius_shared_stock.admin.pending_changes'),
            service('altmenorg_sylius_shared_stock.store'),
            service('request_stack'),
            $allowNegative,
        ])
        ->tag('kernel.event_listener', ['event' => 'sylius.product.post_create'])
        ->tag('kernel.event_listener', ['event' => 'sylius.product.post_update']);

    $services->set('altmenorg_sylius_shared_stock.listener.admin_grid', AdminGridListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.grid.admin_product', 'method' => 'onProductGrid'])
        ->tag('kernel.event_listener', ['event' => 'sylius.grid.admin_product_variant', 'method' => 'onProductVariantGrid']);

    $services->set('altmenorg_sylius_shared_stock.twig.extension', SharedStockExtension::class)
        ->tag('twig.extension');
};
