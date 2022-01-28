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
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use Ramsey\Uuid\Uuid;

use function array_walk;
use function basename;
use function mb_substr;
use function str_replace;
use function sprintf;

/**
 * @internal
 */
final class FileUpdater
{
    private FilePropertyConfigurationResolver $configurationResolver;
    private FileManager $fileManager;
    private FileLoader $fileLoader;
    private FileRemover $fileRemover;

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

                $this->clearCurrentFileIfExists($configuration->getPathPrefix(), $currentFile);
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
        $filePropertyReflection = $configuration->getFilePropertyReflection();
        $file = null;
        if (true === $filePropertyReflection->isInitialized($entity)) {
            $file = $filePropertyReflection->getValue($entity);
        }
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

        if (true === $newFile instanceof UploadedWebFile) {
            // These always need to be transformed into target filesystem
            return true;
        }

        // If filesystems and paths are the same, we assume it is the same file
        return $newFile->getFileSystemName() !== $currentFile->getFileSystemName()
            || $newFile->getPath() !== $currentFile->getPath()
        ;
    }

    private function clearCurrentFileIfExists(string $pathPrefix, ?WebFile $oldFile): void
    {
        if (null === $oldFile) {
            return;
        }

        $this->fileRemover->add($pathPrefix, $oldFile);
    }

    private function setNewFile(object $entity, WebFile $file, FilePropertyConfiguration $configuration): void
    {
        if (true === $file instanceof UploadedWebFile) {
            $file = $this->writeUploadedFileToTargetFilesystem($file, $configuration);
        } else {
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

    private function writeUploadedFileToTargetFilesystem(
        UploadedWebFile $file,
        FilePropertyConfiguration $configuration
    ): WebFile {
        return $this->fileManager->copyFromStream(
            $file->getStream(),
            $configuration->getFileSystemName(),
            $this->createFilesystemPath($configuration, $file->getOriginalName())
        );
    }

    private function createFilesystemPath(FilePropertyConfiguration $configuration, string $filename): string
    {
        // Mitigate filesystem limits for maximum number of subdirectories
        $uuid = str_replace('-', '', Uuid::uuid4()->toString());
        $splitUuid = sprintf(
            '%s/%s/%s/%s',
            mb_substr($uuid, 0, 3),
            mb_substr($uuid, 3, 3),
            mb_substr($uuid, 6, 3),
            mb_substr($uuid, 9)
        );

        return sprintf(
            '%s/%s/%s',
            $configuration->getPathPrefix(),
            $splitUuid,
            $filename
        );
    }
}
