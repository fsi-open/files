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
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\WebFile;
use Ramsey\Uuid\Uuid;
use function array_walk;
use function basename;
use function mb_strpos;
use function sprintf;

/**
 * @internal
 */
final class FileUpdater
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
     * @var FileRemover
     */
    private $fileRemover;

    public function __construct(
        FilePropertyConfigurationResolver $configurationResolver,
        FileManager $fileManager,
        FileLoader $fileLoader,
        FileRemover $fileRemover
    ) {
        $this->configurationResolver = $configurationResolver;
        $this->fileManager = $fileManager;
        $this->fileLoader = $fileLoader;
        $this->fileRemover = $fileRemover;
    }

    public function updateFiles(object $entity): void
    {
        $configurations = $this->configurationResolver->resolveEntity($entity);

        array_walk(
            $configurations,
            function (FilePropertyConfiguration $configuration, $key, object $entity): void {
                $newFile = $this->readNewFileFromEntity($configuration, $entity);
                $currentFile = $this->fileLoader->fromEntity($configuration, $entity);
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

        $this->fileRemover->add($oldFile);
    }

    private function setNewFile(object $entity, WebFile $file, FilePropertyConfiguration $configuration): void
    {
        if (false === $this->isFileSameFilesystemAsInConfiguration($file, $configuration)) {
            $file = $this->copyFileToConfigurationFilesystem($file, $configuration);
        }

        $configuration->getPathPropertyReflection()->setValue($entity, $file->getPath());
        $configuration->getFilePropertyReflection()->setValue($entity, $file);
    }

    private function copyFileToConfigurationFilesystem(WebFile $file, FilePropertyConfiguration $configuration): WebFile
    {
        return $this->fileManager->copy(
            $file,
            $configuration->getFileSystemName(),
            $this->createFilesystemPath($configuration, basename($file->getPath()))
        );
    }

    private function isFileSameFilesystemAsInConfiguration(
        WebFile $file,
        FilePropertyConfiguration $configuration
    ): bool {
        return $file->getFileSystemName() === $configuration->getFileSystemName()
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
