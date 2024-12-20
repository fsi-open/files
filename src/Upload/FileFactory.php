<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Upload;

use FSi\Component\Files\DirectlyUploadedWebFile;
use FSi\Component\Files\TemporaryWebFile;
use FSi\Component\Files\UploadedWebFile;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

interface FileFactory
{
    public function createFromPath(string $path, ?string $targetName = null): UploadedWebFile;
    public function createFromUri(UriInterface $uri, ?string $targetName = null): UploadedWebFile;
    public function create(
        StreamInterface $stream,
        string $originalName,
        string $type,
        int $size,
        ?int $error
    ): UploadedWebFile;
    public function createTemporary(string $fileSystemName, string $path): TemporaryWebFile;
    public function createDirectlyUploaded(string $fileSystemName, string $path): DirectlyUploadedWebFile;
}
