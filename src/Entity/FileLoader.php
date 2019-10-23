<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Entity;

use Assert\Assertion;
use FSi\Component\Files\FileFactory;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\WebFile;

class FileLoader
{
    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * @var FilePropertyConfigurationResolver
     */
    private $configurationResolver;

    public function __construct(FileFactory $fileFactory, FilePropertyConfigurationResolver $configurationResolver)
    {
        $this->fileFactory = $fileFactory;
        $this->configurationResolver = $configurationResolver;
    }

    public function loadEntityFiles(object $entity): void
    {
        $configurations = $this->configurationResolver->resolveEntity($entity);
        array_walk(
            $configurations,
            function (FilePropertyConfiguration $configuration, $key, object $entity): void {
                $configuration->getFilePropertyReflection()->setValue(
                    $entity,
                    $this->fromEntity($configuration, $entity)
                );
            },
            $entity
        );
    }

    public function fromEntity(FilePropertyConfiguration $configuration, object $entity): ?WebFile
    {
        Assertion::isInstanceOf($entity, $configuration->getEntityClass());

        $path = $configuration->getPathPropertyReflection()->getValue($entity);
        if (null === $path) {
            return null;
        }

        return $this->fileFactory->createFromPath($configuration->getFileSystemName(), $path);
    }
}
