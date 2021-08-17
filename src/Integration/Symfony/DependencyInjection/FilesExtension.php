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

use function mb_strlen;
use function sprintf;
use function trim;

final class FilesExtension extends Extension
{
    /**
     * @param array{
     *   entities: array<string, array{
     *     filesystem: string,
     *     prefix: string,
     *     fields: array<string, array{
     *         name: string,
     *         prefix: string,
     *         filesystem?: string,
     *         pathField?: string
     *     }>
     *   }>
     * } $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader(
            $container,
            new FileLocator(sprintf('%s/../Resources/config/services', __DIR__))
        );
        $loader->load('services.xml');

        /**
         * @var array{
         *   entities: array<string, array{
         *     filesystem: string,
         *     prefix: string,
         *     fields: array<string, array{
         *         name: string,
         *         prefix: string,
         *         filesystem?: string,
         *         pathField?: string
         *     }>
         *   }>
         * } $configuration
         */
        $configuration = $this->processConfiguration(new Configuration(), $configs);
        $entityConfigurations = $this->createEntitiesFieldsConfigurations($configuration);

        $resolverDefinition = $container->getDefinition(FilePropertyConfigurationResolver::class);
        $resolverDefinition->replaceArgument('$configurations', $entityConfigurations);
    }

    public function getAlias(): string
    {
        return 'fsi_files';
    }

    /**
     * @param array{
     *   entities: array<string, array{
     *     filesystem: string,
     *     prefix: string,
     *     fields: array<string, array{
     *         name: string,
     *         prefix: string,
     *         filesystem?: string,
     *         pathField?: string
     *     }>
     *   }>
     * } $configuration
     * @return array<Definition>
     */
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

            foreach ($entityConfiguration['fields'] as $fieldConfiguration) {
                $fieldsConfiguration[] = $this->createFilePropertyConfigurationDefinition(
                    $class,
                    $filesystem,
                    $filesystemPrefix,
                    $fieldConfiguration
                );
            }
        }

        return $fieldsConfiguration;
    }

    /**
     * @param string $class
     * @param string $filesystem
     * @param string $filesystemPrefix
     * @param array{name: string, prefix: string, filesystem?: string, pathField?: string} $fieldConfiguration
     * @return Definition
     */
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
            $filePrefix ?? $filesystemPrefix,
        ]);

        return $definition;
    }

    private function startsOrEndsWithSlashes(string $prefix): bool
    {
        return mb_strlen($prefix) !== mb_strlen(trim($prefix, '/'));
    }
}
