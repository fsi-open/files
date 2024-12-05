<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Entity;

use FSi\Component\Files\Entity\Event\WebFileRemoved;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\WebFile;
use Psr\EventDispatcher\EventDispatcherInterface;

use function array_key_exists;
use function array_walk;
use function dirname;

/**
 * @internal
 */
final class FileRemover
{
    private FilePropertyConfigurationResolver $configurationResolver;
    private FileManager $fileManager;
    private FileLoader $fileLoader;
    /**
     * @var array<string, list<array{configuration: FilePropertyConfiguration, entity: object, file: WebFile}>>
     */
    private array $filesToRemove;
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

    public function add(FilePropertyConfiguration $configuration, object $entity, WebFile $file): void
    {
        $pathPrefix = $configuration->getPathPrefix();

        if (false === array_key_exists($pathPrefix, $this->filesToRemove)) {
            $this->filesToRemove[$pathPrefix] = [];
        }

        $this->filesToRemove[$pathPrefix][] = ['configuration' => $configuration, 'entity' => $entity, 'file' => $file];
    }

    public function flush(): void
    {
        // Remove files
        array_walk($this->filesToRemove, function (array $fileEntries): void {
            array_walk($fileEntries, function (array $fileEntry): void {
                $this->fileManager->remove($fileEntry['file']);
            });
        });

        // Clear empty directories left after file removal
        array_walk($this->filesToRemove, function (array $fileEntries, string $pathPrefix): void {
            array_walk(
                $fileEntries,
                function (array $fileEntry, $key, string $pathPrefix): void {
                    $this->removeParentDirectoryIfEmpty(
                        $fileEntry['file']->getFileSystemName(),
                        $pathPrefix,
                        $fileEntry['file']->getPath()
                    );
                },
                $pathPrefix
            );
        });

        if (null !== $this->eventDispatcher) {
            // notify about removed files
            array_walk($this->filesToRemove, function (array $files): void {
                array_walk($files, function (array $file): void {
                    $this->eventDispatcher?->dispatch(
                        new WebFileRemoved($file['configuration'], $file['entity'], $file['file'])
                    );
                });
            });
        }

        $this->filesToRemove = [];
    }

    private function removeParentDirectoryIfEmpty(
        string $fileSystemName,
        string $pathPrefix,
        string $path
    ): void {
        $parentDirectory = dirname($path);
        if ($pathPrefix === $parentDirectory) {
            return;
        }

        if (true === $this->fileManager->removeDirectoryIfEmpty($fileSystemName, $parentDirectory)) {
            $this->removeParentDirectoryIfEmpty($fileSystemName, $pathPrefix, $parentDirectory);
        }
    }
}
