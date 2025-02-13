<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem;

trait WebFileImplementation
{
    private string $fileSystemName;
    private string $path;

    public function __construct(string $fileSystemName, string $path)
    {
        $this->fileSystemName = $fileSystemName;
        $this->path = $path;
    }

    public function getFileSystemName(): string
    {
        return $this->fileSystemName;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
