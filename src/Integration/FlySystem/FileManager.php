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
use FSi\Component\Files\Integration\FlySystem;
use League\Flysystem\Exception;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\MountManager;
use function basename;
use function count;
use function dirname;
use function is_resource;
use function sprintf;

final class FileManager implements Files\FileManager
{
    /**
     * @var MountManager
     */
    private $mountManager;

    public function __construct(MountManager $mountManager)
    {
        $this->mountManager = $mountManager;
    }

    public function create(string $fileSystemName, string $path, string $contents): Files\WebFile
    {
        $this->mountManager->getFilesystem($fileSystemName)->put($path, $contents);
        return $this->load($fileSystemName, $path);
    }

    public function copy(
        Files\WebFile $sourceFile,
        string $targetFileSystemName,
        string $targetPath
    ): Files\WebFile {
        $this->writeStream(
            $targetFileSystemName,
            $targetPath,
            $this->readStream($sourceFile)
        );

        return $this->load($targetFileSystemName, $targetPath);
    }

    public function load(string $fileSystemName, string $path): Files\WebFile
    {
        if (false === $this->mountManager->getFilesystem($fileSystemName)->has($path)) {
            throw new FileNotFoundException($path);
        }

        return new FlySystem\WebFile($fileSystemName, $path);
    }

    public function exists(Files\WebFile $file): bool
    {
        return $this->fileSystemForFile($file)->has($file->getPath());
    }

    public function filename(Files\WebFile $file): string
    {
        return basename($file->getPath());
    }

    public function contents(Files\WebFile $file): string
    {
        $contents = $this->fileSystemForFile($file)->read($file->getPath());
        if (false === $contents) {
            throw new Exception(sprintf(
                'Unable to read contents of file "%s" from filesystem "%s"',
                $file->getPath(),
                $file->getFileSystemName()
            ));
        }

        return $contents;
    }

    public function remove(Files\WebFile $file): void
    {
        $filesystem = $this->fileSystemForFile($file);
        $filesystem->delete($file->getPath());

        $directory = dirname($file->getPath());
        if ('.' !== $directory && 0 === count($filesystem->listContents($directory))) {
            $filesystem->deleteDir($directory);
        }
    }

    private function readStream(Files\WebFile $file)
    {
        $stream = $this->fileSystemForFile($file)->readStream($file->getPath());
        if (false === is_resource($stream)) {
            throw new Exception(sprintf(
                'Unable to read stream from file "%s" of filesystem "%s"',
                $file->getPath(),
                $file->getFileSystemName()
            ));
        }

        return $stream;
    }

    private function writeStream(string $fileSystemPrefix, string $path, $stream): void
    {
        $this->mountManager->getFilesystem($fileSystemPrefix)->putStream($path, $stream);
    }

    private function fileSystemForFile(Files\WebFile $file): FilesystemInterface
    {
        return $this->mountManager->getFilesystem($file->getFileSystemName());
    }
}
