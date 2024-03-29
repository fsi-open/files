<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem;

use BadMethodCallException;
use FSi\Component\Files;
use Psr\Http\Message\StreamInterface;

final class UploadedWebFile implements Files\UploadedWebFile
{
    private StreamInterface $stream;
    private string $originalName;
    private string $mimeType;
    private int $size;
    private ?int $error;

    public function __construct(
        StreamInterface $stream,
        string $originalName,
        string $mimeType,
        int $size,
        ?int $error
    ) {
        $this->stream = $stream;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->error = $error;
    }

    public function getFileSystemName(): string
    {
        throw new BadMethodCallException('Method unimplemented');
    }

    public function getPath(): string
    {
        throw new BadMethodCallException('Method unimplemented');
    }

    public function getStream(): StreamInterface
    {
        return $this->stream;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): ?int
    {
        return $this->error;
    }
}
