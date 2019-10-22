<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\EventListener;

use Assert\Assertion;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\Integration\FlySystem\FilePropertyConfiguration;
use FSi\Component\Files\Integration\FlySystem\FilePropertyConfigurationResolver;
use FSi\Component\Files\Integration\FlySystem\WebFile;
use Ramsey\Uuid\Uuid;
use function array_walk;
use function basename;
use function mb_strpos;
use function sprintf;

class EntityFileUpdater
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
     * @var EntityFileLoader
     */
    private $entityFileLoader;

    /**
     * @var EntityFileRemover
     */
    private $entityFileRemover;

    /**
     * @var string
     */
    private $temporaryFileSystemPrefix;

    public function __construct(
        FilePropertyConfigurationResolver $configurationResolver,
        FileManager $fileManager,
        EntityFileLoader $entityFileLoader,
        EntityFileRemover $entityFileRemover,
        string $temporaryFileSystemPrefix
    ) {
        $this->configurationResolver = $configurationResolver;
        $this->fileManager = $fileManager;
        $this->entityFileLoader = $entityFileLoader;
        $this->entityFileRemover = $entityFileRemover;
        $this->temporaryFileSystemPrefix = $temporaryFileSystemPrefix;
    }

    public function updateFiles(object $entity): void
    {
        $configurations = $this->configurationResolver->resolveEntity($entity);

        array_walk($configurations, function (FilePropertyConfiguration $configuration) use ($entity): void {
            /** @var WebFile|null $newFile */
            $newFile = $configuration->getFilePropertyReflection()->getValue($entity);
            Assertion::nullOrIsInstanceOf($newFile, WebFile::class);

            $oldFile = $this->entityFileLoader->fromEntity($configuration, $entity);
            if (null === $newFile && null === $oldFile) {
                return;
            }

            if (null !== $newFile && null !== $oldFile && $newFile->getPath() === $oldFile->getPath()) {
                return;
            }

            if (null !== $oldFile) {
                $this->entityFileRemover->add($oldFile);
            }

            if (null === $newFile) {
                $configuration->getPathPropertyReflection()->setValue($entity, null);

                return;
            }

            $newFile = $this->prepareFile($newFile, $configuration);

            $configuration->getPathPropertyReflection()->setValue($entity, $newFile->getPath());
            $configuration->getFilePropertyReflection()->setValue($entity, $newFile);
        });
    }

    private function prepareFile(WebFile $file, FilePropertyConfiguration $configuration): WebFile
    {
        if (true === $this->isFileSameFilesystemAsInConfiguration($file, $configuration)) {
            return $file;
        }

        $path = $this->path($configuration, basename($file->getPath()));
        $this->fileManager->writeStream(
            $configuration->getFileSystemPrefix(),
            $path,
            $this->fileManager->readStream($file)
        );

        if ($this->temporaryFileSystemPrefix === $file->getFileSystemPrefix()) {
            $this->entityFileRemover->add($file);
        }

        return new WebFile($configuration->getFileSystemPrefix(), $path);
    }

    private function isFileSameFilesystemAsInConfiguration(
        WebFile $file,
        FilePropertyConfiguration $configuration
    ): bool {
        return $file->getFileSystemPrefix() === $configuration->getFileSystemPrefix()
            && 0 === mb_strpos($file->getPath(), $configuration->getPathPrefix())
        ;
    }

    private function path(FilePropertyConfiguration $configuration, string $filename): string
    {
        return sprintf(
            '%s/%s/%s',
            $configuration->getPathPrefix(),
            Uuid::uuid4()->toString(),
            $filename
        );
    }
}
