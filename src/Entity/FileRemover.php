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
use FSi\Component\Files\Entity\Event\WebFileRemoved;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\WebFile;
use Psr\EventDispatcher\EventDispatcherInterface;

use function array_walk;
use function dirname;
use function str_starts_with;

/**
 * @internal
 */
final class FileRemover
{
    private FilePropertyConfigurationResolver $configurationResolver;
    private FileManager $fileManager;
    private FileLoader $fileLoader;
    /**
     * @var list<array{configuration: FilePropertyConfiguration|null, entity: object, file: WebFile}>
     */
    private array $filesToRemove;
    /**
     * @var list<array{fileSystemName: string, pathPrefix: string|null, path: string}>
     */
    private array $directoriesToRemove;
    private ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        FilePropertyConfigurationResolver $configurationResolver,
        FileManager $fileManager,
        FileLoader $fileLoader,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->configurationResolver = $configurationResolver;
        $this->fileManager = $fileManager;
        $this->fileLoader = $fileLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->filesToRemove = [];
        $this->directoriesToRemove = [];
    }

    public function clearEntityFiles(object $entity): void
    {
        $configurations = $this->configurationResolver->resolveEntity($entity);

        array_walk(
            $configurations,
            function (FilePropertyConfiguration $configuration, $key, object $entity): void {
                $file = $this->fileLoader->fromEntity($configuration, $entity);
                if (null === $file) {
                    return;
                }

                $this->fileLoader->checkFileExistenceIfEnabled($configuration, $file);
                $this->add($configuration, $entity, $file);
            },
            $entity
        );
    }

    public function add(?FilePropertyConfiguration $configuration, object $entity, WebFile $file): void
    {
        if (null !== $configuration) {
            Assertion::same($configuration->getFileSystemName(), $file->getFileSystemName());
            Assertion::isInstanceOf($entity, $configuration->getEntityClass());
        }

        $this->filesToRemove[] = ['configuration' => $configuration, 'entity' => $entity, 'file' => $file];
        $this->addEmptyDirectory(
            $file->getFileSystemName(),
            $configuration?->getPathPrefix(),
            dirname($file->getPath())
        );
    }

    public function addEmptyDirectory(string $fileSystemName, ?string $pathPrefix, string $path): void
    {
        if (null !== $pathPrefix && ($path === $pathPrefix || false === str_starts_with($path, $pathPrefix))) {
            return;
        }

        $this->directoriesToRemove[] = [
            'fileSystemName' => $fileSystemName,
            'pathPrefix' => $pathPrefix,
            'path' => $path
        ];
    }

    public function flush(): void
    {
        // Remove files
        array_walk($this->filesToRemove, function (array $fileEntry): void {
            $this->fileManager->remove($fileEntry['file']);
        });

        // Clear empty directories
        array_walk($this->directoriesToRemove, function (array $directoryEntry): void {
            $this->removeDirectoryIfEmpty(
                $directoryEntry['fileSystemName'],
                $directoryEntry['pathPrefix'],
                $directoryEntry['path']
            );
        });

        if (null !== $this->eventDispatcher) {
            // notify about removed files
            array_walk($this->filesToRemove, function (array $file): void {
                if (null === $file['configuration']) {
                    return;
                }

                $this->eventDispatcher?->dispatch(
                    new WebFileRemoved($file['configuration'], $file['entity'], $file['file'])
                );
            });
        }

        $this->filesToRemove = [];
        $this->directoriesToRemove = [];
    }

    private function removeDirectoryIfEmpty(
        string $fileSystemName,
        ?string $pathPrefix,
        string $path
    ): void {
        if (null !== $pathPrefix && $pathPrefix === $path) {
            return;
        }

        if (true === $this->fileManager->removeDirectoryIfEmpty($fileSystemName, $path)) {
            $this->removeDirectoryIfEmpty($fileSystemName, $pathPrefix, dirname($path));
        }
    }
}
