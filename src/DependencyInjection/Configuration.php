<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('altmenorg_sylius_shared_stock');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('allow_negative')
                    ->info('Let the shop hold and sell beyond the shared stock on hand (backorders). The admin follows the same rule.')
                    ->defaultFalse()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
