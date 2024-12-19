<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\App\Entity;

use FSi\Component\Files\WebFile;

class FileEntity
{
    /**
     * @var int|null
     */
    private $id;

    /**
     * @var WebFile|null
     */
    private $file;

    /**
     * @var string|null
     */
    private $filePath;

    /**
     * @var WebFile|null
     */
    private $anotherFile;

    /**
     * @var string|null
     */
    private $anotherFileKey;

    /**
     * @var WebFile|null
     */
    private $privateFile;

    /**
     * @var string|null
     */
    private $privateFileKey;

    /**
     * @var WebFile|null
     */
    private $temporaryFile;

    /**
     * @var string|null
     */
    private $temporaryFileKey;

    /**
     * @var WebFile|null
     */
    private $directFile;

    /**
     * @var string|null
     */
    private $directFileKey;

    /**
     * @var EmbeddedFile|null
     */
    private $embeddedFile;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getFile(): ?WebFile
    {
        return $this->file;
    }

    public function setFile(?WebFile $file): void
    {
        $this->file = $file;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function getAnotherFile(): ?WebFile
    {
        return $this->anotherFile;
    }

    public function setAnotherFile(?WebFile $anotherFile): void
    {
        $this->anotherFile = $anotherFile;
    }

    public function getAnotherFileKey(): ?string
    {
        return $this->anotherFileKey;
    }

    public function setAnotherFileKey(?string $anotherFileKey): void
    {
        $this->anotherFileKey = $anotherFileKey;
    }

    public function getPrivateFile(): ?WebFile
    {
        return $this->privateFile;
    }

    public function setPrivateFile(?WebFile $privateFile): void
    {
        $this->privateFile = $privateFile;
    }

    public function getPrivateFileKey(): ?string
    {
        return $this->privateFileKey;
    }

    public function setPrivateFileKey(?string $privateFileKey): void
    {
        $this->privateFileKey = $privateFileKey;
    }

    public function getTemporaryFile(): ?WebFile
    {
        return $this->temporaryFile;
    }

    public function setTemporaryFile(?WebFile $temporaryFile): void
    {
        $this->temporaryFile = $temporaryFile;
    }

    public function getTemporaryFileKey(): ?string
    {
        return $this->temporaryFileKey;
    }

    public function setTemporaryFileKey(?string $temporaryFileKey): void
    {
        $this->temporaryFileKey = $temporaryFileKey;
    }

    public function getDirectFile(): ?WebFile
    {
        return $this->directFile;
    }

    public function setDirectFile(?WebFile $directFile): void
    {
        $this->directFile = $directFile;
    }

    public function getDirectFileKey(): ?string
    {
        return $this->directFileKey;
    }

    public function setDirectFileKey(?string $directFileKey): void
    {
        $this->directFileKey = $directFileKey;
    }

    public function getEmbeddedFile(): ?EmbeddedFile
    {
        return $this->embeddedFile;
    }

    public function setEmbeddedFile(?EmbeddedFile $embeddedFile): void
    {
        $this->embeddedFile = $embeddedFile;
    }
}
