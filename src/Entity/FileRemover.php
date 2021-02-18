<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Entity;

use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\WebFile;

use function array_key_exists;
use function array_walk;
use function dirname;

/**
 * @internal
 */
final class FileRemover
{
    /**
     * @var FilePropertyConfigurationResolver
     */
    private $configurationResolver;

    /**
     * @var FileManager
     */
    private $fileManager;

    /**
     * @var FileLoader
     */
    private $fileLoader;

    /**
     * @var array<string, array<WebFile>>
     */
    private $filesToRemove;

    public function __construct(
        FilePropertyConfigurationResolver $configurationResolver,
        FileManager $fileManager,
        FileLoader $fileLoader
    ) {
        $this->configurationResolver = $configurationResolver;
        $this->fileManager = $fileManager;
        $this->fileLoader = $fileLoader;
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

                $this->add($configuration->getPathPrefix(), $file);
            },
            $entity
        );
    }

    public function add(string $pathPrefix, WebFile $file): void
    {
        if (false === array_key_exists($pathPrefix, $this->filesToRemove)) {
            $this->filesToRemove[$pathPrefix] = [];
        }

        $this->filesToRemove[$pathPrefix][] = $file;
    }

    public function flush(): void
    {
        // Remove files
        array_walk($this->filesToRemove, function (array $files): void {
            array_walk($files, function (WebFile $file): void {
                $this->fileManager->remove($file);
            });
        });

        // Clear empty directories left after file removal
        array_walk($this->filesToRemove, function (array $files, string $pathPrefix): void {
            array_walk(
                $files,
                function (WebFile $file, $key, string $pathPrefix): void {
                    $this->removeParentDirectoryIfEmpty(
                        $file->getFileSystemName(),
                        $pathPrefix,
                        $file->getPath()
                    );
                },
                $pathPrefix
            );
        });

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
