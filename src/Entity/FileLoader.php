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
use FSi\Component\Files\Exception\FileNotFoundException;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FileManagerConfigurator\FileExistenceChecksConfigurator;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\WebFile;

use function array_key_exists;
use function array_walk;

/**
 * @internal
 */
final class FileLoader implements FileExistenceChecksConfigurator
{
    private FileManager $fileManager;
    private FilePropertyConfigurationResolver $configurationResolver;
    private bool $fileExistenceChecksOnLoad;
    /**
     * @var array<class-string<object>, bool>
     */
    private array $classFileExistenceChecksOnLoad;

    public function __construct(
        FileManager $fileManager,
        FilePropertyConfigurationResolver $configurationResolver
    ) {
        $this->fileManager = $fileManager;
        $this->configurationResolver = $configurationResolver;
        $this->fileExistenceChecksOnLoad = true;
        $this->classFileExistenceChecksOnLoad = [];
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
        $className = $configuration->getEntityClass();
        Assertion::isInstanceOf($entity, $className);

        $disabledFileExistenceChecksOnLoad = $configuration->isFileExistenceChecksOnLoadDisabled();
        // Do not overwrite the option if it was manually set via disable/enableClassFileExistenceChecksOnLoad
        if (false === array_key_exists($className, $this->classFileExistenceChecksOnLoad)) {
            $this->classFileExistenceChecksOnLoad[$className] = $disabledFileExistenceChecksOnLoad;
        }

        $pathPropertyReflection = $configuration->getPathPropertyReflection();
        if (false === $pathPropertyReflection->isInitialized($entity)) {
            return null;
        }

        $path = $pathPropertyReflection->getValue($entity);
        if (null === $path) {
            return null;
        }

        $file = $this->fileManager->load($configuration->getFileSystemName(), $path);
        if (null !== $file) {
            $this->checkFileExistenceIfEnabled(
                $file,
                $this->classFileExistenceChecksOnLoad[$className]
            );
        }

        return $file;
    }

    public function disableFileExistanceChecksOnLoad(): void
    {
        $this->disableFileExistenceChecksOnLoad();
    }

    public function enableFileExistanceChecksOnLoad(): void
    {
        $this->enableFileExistenceChecksOnLoad();
    }

    public function disableFileExistenceChecksOnLoad(): void
    {
        $this->fileExistenceChecksOnLoad = false;
    }

    public function enableFileExistenceChecksOnLoad(): void
    {
        $this->fileExistenceChecksOnLoad = true;
    }

    /**
     * @param class-string<object> $className
     * @return void
     */
    public function disableClassFileExistenceChecksOnLoad(string $className): void
    {
        $this->classFileExistenceChecksOnLoad[$className] = false;
    }

    /**
     * @param class-string<object> $className
     * @return void
     */
    public function enableClassFileExistenceChecksOnLoad(string $className): void
    {
        $this->classFileExistenceChecksOnLoad[$className] = true;
    }

    private function checkFileExistenceIfEnabled(WebFile $file, bool $disabledByConfiguration): void
    {
        if (false === $this->fileExistenceChecksOnLoad && false === $disabledByConfiguration) {
            return;
        }

        if (false === $this->fileManager->exists($file)) {
            throw FileNotFoundException::forFile($file);
        }
    }
}
