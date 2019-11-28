<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem\Upload;

use FSi\Component\Files;
use FSi\Component\Files\Integration\FlySystem;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use const UPLOAD_ERR_OK;
use function basename;
use function file_exists;
use function fopen;
use function mime_content_type;

final class FileFactory implements Files\Upload\FileFactory
{
    public function create(
        StreamInterface $stream,
        string $originalName,
        string $type,
        int $size,
        ?int $error
    ): Files\UploadedWebFile {
        return new FlySystem\UploadedWebFile(
            $stream,
            $originalName,
            $type,
            $size,
            $error
        );
    }

    public function createFromPath(string $path, ?string $targetName = null): Files\UploadedWebFile
    {
        if (false === file_exists($path)) {
            throw new RuntimeException("File at path \"{$path}\" does not exist.");
        }

        $fileHandle = fopen($path, 'r');
        if (false === $fileHandle) {
            throw new RuntimeException("File at path \"{$path}\" could not be read.");
        }

        $mimeType = mime_content_type($path);
        if (false === $mimeType) {
            throw new RuntimeException("Unable to read mime type from file at path \"{$path}\".");
        }

        $stream = new Stream($fileHandle);
        $size = $stream->getSize();
        if (null === $size) {
            throw new RuntimeException("Unable to read size of file at path \"{$path}\".");
        }

        return new FlySystem\UploadedWebFile(
            $stream,
            $targetName ?? basename($path),
            $mimeType,
            $size,
            UPLOAD_ERR_OK
        );
    }
}
