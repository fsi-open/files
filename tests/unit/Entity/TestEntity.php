<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\Entity;

use FSi\Component\Files\WebFile;

class TestEntity
{
    /**
     * @var WebFile|null
     */
    private $file;

    /**
     * @var string|null
     */
    private $filePath;

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
