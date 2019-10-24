<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem;

use FSi\Component\Files;
use FSi\Component\Files\Entity\FileRemover;
use FSi\Component\Files\Integration\FlySystem;

final class FileFactory implements Files\FileFactory
{
    /**
     * @var FileManager
     */
    private $fileManager;

    /**
     * @var FileRemover
     */
    private $entityFileRemover;

    /**
     * @var string
     */
    private $temporaryFileSystemPrefix;

    public function __construct(
        FileManager $fileManager,
        FileRemover $entityFileRemover,
        string $temporaryFileSystemPrefix
    ) {
        $this->fileManager = $fileManager;
        $this->entityFileRemover = $entityFileRemover;
        $this->temporaryFileSystemPrefix = $temporaryFileSystemPrefix;
    }

    public function createFromPath(string $fileSystemName, string $path): Files\WebFile
    {
        return new FlySystem\WebFile($fileSystemName, $path);
    }

    public function copy(
        Files\WebFile $sourceFile,
        string $targetFileSystemName,
        string $targetPath
    ): Files\WebFile {
        $this->fileManager->writeStream(
            $targetFileSystemName,
            $targetPath,
            $this->fileManager->readStream($sourceFile)
        );

        if ($this->temporaryFileSystemPrefix === $sourceFile->getFileSystemName()) {
            $this->entityFileRemover->add($sourceFile);
        }

        return $this->createFromPath($targetFileSystemName, $targetPath);
    }
}
