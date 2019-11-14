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
use function array_walk;

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
     * @var WebFile[]
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
        $this->filesToRemove[$pathPrefix] = $file;
    }

    public function flush(): void
    {
        array_walk($this->filesToRemove, function (WebFile $file): void {
            $this->fileManager->remove($file);
        });

        array_walk($this->filesToRemove, function (WebFile $file, string $pathPrefix): void {
            $this->fileManager->removeFileEmptyParentDirectories($pathPrefix, $file);
        });

        $this->filesToRemove = [];
    }
}
