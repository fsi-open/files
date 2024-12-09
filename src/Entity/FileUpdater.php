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
use FSi\Component\Files\DirectlyUploadedWebFile;
use FSi\Component\Files\Entity\Event\WebFileUsed;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\TemporaryWebFile;
use FSi\Component\Files\Upload\FilePathGenerator;
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

use function array_walk;
use function basename;
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
    private ?EventDispatcherInterface $eventDispatcher;
    /**
     * @var list<array{configuration:FilePropertyConfiguration, entity:object, file:WebFile}>
     */
    private array $filesUsed = [];

    public function __construct(
        FilePropertyConfigurationResolver $configurationResolver,
        FileManager $fileManager,
        FileLoader $fileLoader,
        FileRemover $fileRemover,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->configurationResolver = $configurationResolver;
        $this->fileManager = $fileManager;
        $this->fileLoader = $fileLoader;
        $this->fileRemover = $fileRemover;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function updateFiles(object $entity): void
    {
        $configurations = $this->configurationResolver->resolveEntity($entity);

        array_walk(
            $configurations,
            function (FilePropertyConfiguration $configuration, $key, object $entity): void {
                $newFile = $this->readNewFileFromEntity($configuration, $entity);
                $currentFile = $this->fileLoader->fromEntity($configuration, $entity);
                if (null !== $currentFile) {
                    $this->fileLoader->checkFileExistenceIfEnabled($configuration, $currentFile);
                }
                if (false === $this->shouldFilePropertyBeUpdated($newFile, $currentFile)) {
                    return;
                }

                $this->clearCurrentFileIfExists($configuration, $entity, $currentFile);
                if (null !== $newFile) {
                    $this->setNewFile($entity, $newFile, $configuration);
                } else {
                    $configuration->getPathPropertyReflection()->setValue($entity, null);
                }
            },
            $entity
        );
    }

    public function flush(): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        array_walk($this->filesUsed, function (array $fileEntry): void {
            $this->eventDispatcher?->dispatch(
                new WebFileUsed($fileEntry['configuration'], $fileEntry['entity'], $fileEntry['file'])
            );
        });

        $this->filesUsed = [];
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

        if (true === $newFile instanceof UploadedWebFile || true === $newFile instanceof TemporaryWebFile) {
            // These always need to be transformed into target filesystem
            return true;
        }

        // If filesystems and paths are the same, we assume it is the same file
        return $newFile->getFileSystemName() !== $currentFile->getFileSystemName()
            || $newFile->getPath() !== $currentFile->getPath()
        ;
    }

    private function clearCurrentFileIfExists(
        FilePropertyConfiguration $configuration,
        object $entity,
        ?WebFile $oldFile
    ): void {
        if (null === $oldFile) {
            return;
        }

        $this->fileRemover->add($configuration, $entity, $oldFile);
    }

    private function setNewFile(object $entity, WebFile $file, FilePropertyConfiguration $configuration): void
    {
        if (true === $file instanceof DirectlyUploadedWebFile) {
            $newFile = $file;
        } elseif (true === $file instanceof TemporaryWebFile) {
            $newFile = $this->moveTemporaryFileToConfigurationFilesystem($file, $configuration);
        } elseif (true === $file instanceof UploadedWebFile) {
            $newFile = $this->writeUploadedFileToTargetFilesystem($file, $configuration);
        } else {
            $newFile = $this->copyFileToConfigurationFilesystem($file, $configuration);
        }

        $configuration->getPathPropertyReflection()->setValue($entity, $newFile->getPath());
        if (true === $file instanceof DirectlyUploadedWebFile) {
            $newFile = $this->fileLoader->fromEntity($configuration, $entity);
            if (null === $newFile) {
                throw new RuntimeException('Failed to load file after direct upload');
            }

            $this->fileLoader->checkFileExistenceIfEnabled($configuration, $newFile);
        }
        $configuration->getFilePropertyReflection()->setValue($entity, $newFile);
        $this->filesUsed[] = ['configuration' => $configuration, 'entity' => $entity, 'file' => $newFile];
    }

    private function moveTemporaryFileToConfigurationFilesystem(
        TemporaryWebFile $file,
        FilePropertyConfiguration $configuration
    ): WebFile {
        return $this->fileManager->move(
            $file,
            $configuration->getFileSystemName(),
            FilePathGenerator::generate(basename($file->getPath()), $configuration->getPathPrefix())
        );
    }

    private function writeUploadedFileToTargetFilesystem(
        UploadedWebFile $file,
        FilePropertyConfiguration $configuration
    ): WebFile {
        return $this->fileManager->copyFromStream(
            $file->getStream(),
            $configuration->getFileSystemName(),
            FilePathGenerator::generate($file->getOriginalName(), $configuration->getPathPrefix())
        );
    }

    private function copyFileToConfigurationFilesystem(WebFile $file, FilePropertyConfiguration $configuration): WebFile
    {
        return $this->fileManager->copy(
            $file,
            $configuration->getFileSystemName(),
            FilePathGenerator::generate(basename($file->getPath()), $configuration->getPathPrefix())
        );
    }
}
