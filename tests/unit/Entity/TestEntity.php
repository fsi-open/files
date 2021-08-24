<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Entity;

use FSi\Component\Files\WebFile;

class TestEntity
{
    private ?WebFile $file = null;
    private ?string $filePath = null;
    private ?WebFile $file1;
    private ?string $filePath1;

    public function __construct(?WebFile $file)
    {
        $this->file = $file;
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
}
