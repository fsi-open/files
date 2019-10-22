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

final class EntityFileUpdater
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

        array_walk(
            $configurations,
            function (FilePropertyConfiguration $configuration, $key, object $entity): void {
                $newFile = $this->readNewFileFromEntity($configuration, $entity);
                $currentFile = $this->entityFileLoader->fromEntity($configuration, $entity);
                if (false === $this->shouldFilePropertyBeUpdated($newFile, $currentFile)) {
                    return;
                }

                $this->clearCurrentFileIfExists($currentFile);
                if (null !== $newFile) {
                    $this->setNewFile($entity, $newFile, $configuration);
                } else {
                    $configuration->getPathPropertyReflection()->setValue($entity, null);
                }
            },
            $entity
        );
    }

    private function readNewFileFromEntity(FilePropertyConfiguration $configuration, object $entity): ?WebFile
    {
        $file = $configuration->getFilePropertyReflection()->getValue($entity);
        Assertion::nullOrIsInstanceOf($file, WebFile::class, sprintf(
            'Expected an instance of "%s" or null, got "%s" instead',
            WebFile::class,
            true === is_object($file) ? get_class($file) : gettype($file)
        ));

        return $file;
    }

    private function shouldFilePropertyBeUpdated(?WebFile $newFile, ?WebFile $currentFile): bool
    {
        if (null === $newFile && null === $currentFile) {
            // No files to remove or update, nothing to do
            return false;
        }

        if (null === $newFile) {
            // There was an old file, but should be removed
            return true;
        }

        if (null === $currentFile) {
            // There is no file, but a new one was uploaded
            return true;
        }

        // If the paths are the same, we assume it is the same file
        return $newFile->getPath() !== $currentFile->getPath();
    }

    private function clearCurrentFileIfExists(?WebFile $oldFile): void
    {
        if (null === $oldFile) {
            return;
        }

        $this->entityFileRemover->add($oldFile);
    }

    private function setNewFile(object $entity, WebFile $file, FilePropertyConfiguration $configuration): void
    {
        if (false === $this->isFileSameFilesystemAsInConfiguration($file, $configuration)) {
            $file = $this->transformFileToFilesystem($file, $configuration);
        }

        $configuration->getPathPropertyReflection()->setValue($entity, $file->getPath());
        $configuration->getFilePropertyReflection()->setValue($entity, $file);
    }

    private function transformFileToFilesystem(WebFile $file, FilePropertyConfiguration $configuration): WebFile
    {
        $path = $this->createFilesystemPath($configuration, basename($file->getPath()));
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

    private function createFilesystemPath(FilePropertyConfiguration $configuration, string $filename): string
    {
        return sprintf(
            '%s/%s/%s',
            $configuration->getPathPrefix(),
            Uuid::uuid4()->toString(),
            $filename
        );
    }
}
