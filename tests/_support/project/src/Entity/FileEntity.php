<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\App\Entity;

use FSi\Component\Files\WebFile;

final class FileEntity
{
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
}
