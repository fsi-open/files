<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\DependencyInjection;

use Assert\Assertion;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use function array_map;
use function mb_strlen;
use function sprintf;
use function trim;

final class FilesExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader(
            $container,
            new FileLocator(sprintf('%s/../Resources/config/services', __DIR__))
        );
        $loader->load('services.xml');

        $configuration = $this->processConfiguration(new Configuration(), $configs);
        $entityConfigurations = $this->createEntitiesFieldsConfigurations($configuration);

        $resolverDefinition = $container->getDefinition(FilePropertyConfigurationResolver::class);
        $resolverDefinition->replaceArgument('$configurations', $entityConfigurations);
    }

    public function getAlias(): string
    {
        return 'fsi_files';
    }

    private function createEntitiesFieldsConfigurations($configuration): array
    {
        $fieldsConfiguration = [];
        foreach ($configuration['entities'] as $class => $entityConfiguration) {
            $filesystem = $entityConfiguration['filesystem'];
            $filesystemPrefix = $entityConfiguration['prefix'];
            Assertion::false(
                $this->startsOrEndsWithSlashes($filesystemPrefix),
                "Prefix for filesystem \"{$filesystem}\" cannot start or end with a slash"
            );

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

            $fieldsConfiguration = array_merge($fieldsConfiguration, $entityFieldsConfiguration);
        }

        return $fieldsConfiguration;
    }

    private function createFilePropertyConfigurationDefinition(
        string $class,
        string $filesystem,
        string $filesystemPrefix,
        array $fieldConfiguration
    ): Definition {
        $name = $fieldConfiguration['name'];
        $filePrefix = $fieldConfiguration['prefix'];
        if (null !== $filePrefix) {
            Assertion::false(
                $this->startsOrEndsWithSlashes($filePrefix),
                "Prefix for filesystem \"{$filesystem}\" and field \"{$name}\""
                . " cannot start or end with a slash"
            );
        }

        $definition = new Definition(FilePropertyConfiguration::class);
        $definition->setPublic(false);
        $definition->setArguments([
            $class,
            $name,
            $fieldConfiguration['filesystem'] ?? $filesystem,
            $fieldConfiguration['pathField'] ?? "{$name}Path",
            $filePrefix ?? $filesystemPrefix
        ]);

        return $definition;
    }

    private function startsOrEndsWithSlashes(string $prefix): bool
    {
        return mb_strlen($prefix) !== mb_strlen(trim($prefix, '/'));
    }
}
