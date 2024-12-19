<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem;

use Assert\Assertion;
use FSi\Component\Files;
use FSi\Component\Files\Integration\FlySystem;
use FSi\Component\Files\WebFile;
use League\Flysystem\MountManager;
use Psr\Http\Message\StreamInterface;

use function basename;
use function in_array;

final class FileManager implements Files\FileManager
{
    private const EMPTY_OR_ROOT_PATHS = ['', '.', '/'];

    private MountManager $mountManager;

    public function __construct(MountManager $mountManager)
    {
        $this->mountManager = $mountManager;
    }

    public function copy(Files\WebFile $source, string $fileSystemName, string $path): Files\WebFile
    {
        $this->mountManager->copy(
            $this->createPrefixedFilePath($source),
            $this->prefixPathWithFileSystem($fileSystemName, $path)
        );

        return $this->load($fileSystemName, $path);
    }

    public function copyFromStream(
        StreamInterface $stream,
        string $targetFileSystemName,
        string $targetPath
    ): Files\WebFile {
        $detachedStream = $stream->detach();
        Assertion::notNull($detachedStream);
        $this->writeStream($targetFileSystemName, $targetPath, $detachedStream);
        return $this->load($targetFileSystemName, $targetPath);
    }

    public function move(Files\WebFile $source, string $fileSystemName, string $path): Files\WebFile
    {
        $this->mountManager->move(
            $this->createPrefixedFilePath($source),
            $this->prefixPathWithFileSystem($fileSystemName, $path)
        );

        return $this->load($fileSystemName, $path);
    }

    public function load(string $fileSystemName, string $path): Files\WebFile
    {
        return new FlySystem\WebFile($fileSystemName, $path);
    }

    public function exists(Files\WebFile $file): bool
    {
        return $this->mountManager->fileExists($this->createPrefixedFilePath($file));
    }

    public function fileSize(WebFile $file): int
    {
        return $this->mountManager->fileSize($this->createPrefixedFilePath($file));
    }

    public function mimeType(WebFile $file): string
    {
        return $this->mountManager->mimeType($this->createPrefixedFilePath($file));
    }

    public function filename(Files\WebFile $file): string
    {
        return basename($file->getPath());
    }

    public function contents(Files\WebFile $file): string
    {
        return $this->mountManager->read($this->createPrefixedFilePath($file));
    }

    public function remove(Files\WebFile $file): void
    {
        $this->mountManager->delete($this->createPrefixedFilePath($file));
    }

    public function removeDirectoryIfEmpty(string $fileSystemName, string $path): bool
    {
        if (true === $this->isEmptyPathOrRootDirectory($path)) {
            return false;
        }

        $prefixedPath = $this->prefixPathWithFileSystem($fileSystemName, $path);
        if (false === $this->isDirectoryEmpty($prefixedPath)) {
            return false;
        }

        $this->mountManager->deleteDirectory($prefixedPath);
        return true;
    }

    /**
     * @param string $fileSystemPrefix
     * @param string $path
     * @param resource $stream
     */
    private function writeStream(string $fileSystemPrefix, string $path, $stream): void
    {
        $this->mountManager->writeStream(
            $this->prefixPathWithFileSystem($fileSystemPrefix, $path),
            $stream
        );
    }

    private function createPrefixedFilePath(Files\WebFile $file): string
    {
        return $this->prefixPathWithFileSystem($file->getFileSystemName(), $file->getPath());
    }

    private function prefixPathWithFileSystem(string $fileSystem, string $path): string
    {
        return "{$fileSystem}://{$path}";
    }

    private function isDirectoryEmpty(string $path): bool
    {
        foreach ($this->mountManager->listContents($path) as $file) {
            return false;
        }

        return true;
    }

    private function isEmptyPathOrRootDirectory(string $path): bool
    {
        return in_array($path, self::EMPTY_OR_ROOT_PATHS, true);
    }
}
