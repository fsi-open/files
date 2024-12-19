<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\DependencyInjection\CompilerPass;

use Assert\Assertion;
use FSi\Component\Files\DirectUpload\AdapterRegistry;
use FSi\Component\Files\Integration\FlySystem\DirectUpload\S3Adapter;
use Oneup\FlysystemBundle\DependencyInjection\Configuration;
use Oneup\FlysystemBundle\DependencyInjection\OneupFlysystemExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;

final class BuildDirectUploadAdaptersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $flySystemConfigs = $container->getExtensionConfig('oneup_flysystem');
        $flySystemExtension = $container->getExtension('oneup_flysystem');
        Assertion::isInstanceOf($flySystemExtension, OneupFlysystemExtension::class);
        $configuration = $flySystemExtension->getConfiguration($flySystemConfigs, $container);
        Assertion::isInstanceOf($configuration, Configuration::class);
        $processor = new Processor();
        $flySystemConfig = $processor->processConfiguration($configuration, $flySystemConfigs);

        $adapters = [];
        foreach ($flySystemConfig['filesystems'] as $filesystemName => $filesystem) {
            $adapterId = $filesystem['adapter'];
            $adapterConfig = $flySystemConfig['adapters'][$adapterId];
            if (true === array_key_exists('awss3v3', $adapterConfig)) {
                $adapters[$filesystem['mount'] ?? $filesystemName] = new Definition(S3Adapter::class, [
                    new Reference($adapterConfig['awss3v3']['client']),
                    $adapterConfig['awss3v3']['bucket'],
                    $adapterConfig['awss3v3']['path_prefix'] ?? '',
                    $adapterConfig['awss3v3']['options'] ?? [],
                ]);
            }
        }

        $adapterRegistry = $container->getDefinition(AdapterRegistry::class);
        $adapterRegistry->setArgument('$adapters', $adapters);
    }
}
