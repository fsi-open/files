<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files;

use Assert\Assertion;
use RuntimeException;

use function array_key_exists;
use function array_reduce;
use function array_values;
use function array_walk;
use function get_class;
use function sprintf;

final class FilePropertyConfigurationResolver
{
    /**
     * @var array<FilePropertyConfiguration>
     */
    private array $configurations;
    /**
     * @var array<class-string, array<FilePropertyConfiguration>>
     */
    private array $indexedConfigurations;

    /**
     * @param iterable<FilePropertyConfiguration> $configurations
     */
    public function __construct(iterable $configurations)
    {
        if (false === is_array($configurations)) {
            $configurations = iterator_to_array($configurations);
        }

        Assertion::allIsInstanceOf($configurations, FilePropertyConfiguration::class);

        $this->configurations = $configurations;
        $this->indexedConfigurations = [];
        array_walk(
            $configurations,
            function (FilePropertyConfiguration $configuration): void {
                $entityClass = $configuration->getEntityClass();
                $property = $configuration->getFilePropertyName();
                $this->indexedConfigurations[$entityClass][$property] = $configuration;
            }
        );
    }

    public function resolveFileProperty(string $entityClass, string $filePropertyName): FilePropertyConfiguration
    {
        if (
            false === array_key_exists($entityClass, $this->indexedConfigurations)
            || false === array_key_exists($filePropertyName, $this->indexedConfigurations[$entityClass])
        ) {
            throw new RuntimeException(sprintf(
                'There is no file configuration for property "%s" of entity class "%s"',
                $entityClass,
                $filePropertyName
            ));
        }

        return $this->indexedConfigurations[$entityClass][$filePropertyName];
    }

    /**
     * @param object $entity
     * @return array<FilePropertyConfiguration>
     */
    public function resolveEntity(object $entity): array
    {
        $entityClass = get_class($entity);
        if (false === array_key_exists($entityClass, $this->indexedConfigurations)) {
            $this->indexConfigurationForEntity($entity, $entityClass);
        }

        return array_values($this->indexedConfigurations[$entityClass]);
    }

    /**
     * @param object $entity
     * @param class-string $entityClass
     * @return void
     */
    private function indexConfigurationForEntity(object $entity, string $entityClass): void
    {
        $this->indexedConfigurations[$entityClass] = array_reduce(
            $this->configurations,
            function (array $accumulator, FilePropertyConfiguration $configuration) use ($entity): array {
                $configurationEntityClass = $configuration->getEntityClass();
                if (true === $entity instanceof $configurationEntityClass) {
                    $accumulator[] = $configuration;
                }

                return $accumulator;
            },
            []
        );
    }
}
