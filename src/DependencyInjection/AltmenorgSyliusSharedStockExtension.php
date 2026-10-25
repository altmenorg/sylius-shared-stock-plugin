<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Yaml\Yaml;

final class AltmenorgSyliusSharedStockExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('altmenorg_sylius_shared_stock.allow_negative', $config['allow_negative']);

        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);

        if ($container->hasExtension('sylius_twig_hooks')) {
            $hooks = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/twig_hooks.yaml');
            $container->prependExtensionConfig('sylius_twig_hooks', $hooks['sylius_twig_hooks']);
        }
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Altmenorg\SyliusSharedStockPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@AltmenorgSyliusSharedStockPlugin/migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return ['Sylius\Bundle\CoreBundle\Migrations'];
    }
}
