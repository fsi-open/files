<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files;

use Psr\Http\Message\StreamInterface;

interface FileManager
{
    public function copy(WebFile $source, string $fileSystemName, string $path): WebFile;
    public function copyFromStream(
        StreamInterface $stream,
        string $targetFileSystemName,
        string $targetPath
    ): WebFile;
    public function move(WebFile $source, string $fileSystemName, string $path): WebFile;
    public function load(string $fileSystemName, string $path): WebFile;
    public function exists(WebFile $file): bool;
    public function fileSize(WebFile $file): int;
    public function mimeType(WebFile $file): string;
    public function lastModified(WebFile $file): int;
    public function filename(WebFile $file): string;
    public function contents(WebFile $file): string;
    public function remove(WebFile $file): void;
    public function removeDirectoryIfEmpty(string $fileSystemName, string $path): bool;
}
