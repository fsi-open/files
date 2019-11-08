<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\DependencyInjection;

use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use function array_map;
use function sprintf;

final class FilesExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader(
            $container,
            new FileLocator(sprintf('%s/../Resources/config/services', __DIR__))
        );
        $loader->load('services.xml');

        $resolverDefinition = $container->getDefinition(FilePropertyConfigurationResolver::class);
        $entityConfigurations = $this->createEntitiesFieldsConfigurations($configuration);
        $resolverDefinition->replaceArgument('$configurations', $entityConfigurations);
    }

    public function getAlias()
    {
        return 'fsi_files';
    }

    private function createEntitiesFieldsConfigurations($configuration): array
    {
        return array_reduce(
            $configuration['entities'],
            function (array $accumulator, array $entityConfiguration): array {
                $class = $entityConfiguration['class'];
                $filesystem = $entityConfiguration['filesystem'];
                $filesystemPrefix = $entityConfiguration['prefix'];

                $entityFieldsConfiguration = array_map(
                    function (array $fieldConfiguration) use (
                        $class,
                        $filesystem,
                        $filesystemPrefix
                    ): Definition {
                        return $this->createFilePropertyConfigurationDefinition(
                            $class,
                            $filesystem,
                            $filesystemPrefix,
                            $fieldConfiguration
                        );
                    },
                    $entityConfiguration['fields']
                );

                return array_merge($accumulator, $entityFieldsConfiguration);
            },
            []
        );
    }

    private function createFilePropertyConfigurationDefinition(
        string $class,
        string $filesystem,
        string $filesystemPrefix,
        array $fieldConfiguration
    ): Definition {
        $name = $fieldConfiguration['name'];
        $definition = new Definition(FilePropertyConfiguration::class);
        $definition->setPublic(false);
        $definition->setArguments([
            $class,
            $name,
            $filesystem,
            $fieldConfiguration['pathField'] ?? "{$name}Path",
            $fieldConfiguration['prefix'] ?? $filesystemPrefix
        ]);

        return $definition;
    }
}
