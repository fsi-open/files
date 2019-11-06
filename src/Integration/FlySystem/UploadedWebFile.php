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

final class UploadedWebFile implements Files\UploadedWebFile
{
    /**
     * @var string
     */
    private $fileSystemName;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $originalName;

    /**
     * @var string
     */
    private $mimeType;

    /**
     * @var int
     */
    private $size;

    /**
     * @var int|null
     */
    private $error;

    public function __construct(
        string $fileSystemName,
        string $path,
        string $originalName,
        string $mimeType,
        int $size,
        ?int $error
    ) {
        $this->fileSystemName = $fileSystemName;
        $this->path = $path;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->error = $error;
    }

    public function getFileSystemName(): string
    {
        return $this->fileSystemName;
    }

    public function getPath(): string
    {
        return $this->path;
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
