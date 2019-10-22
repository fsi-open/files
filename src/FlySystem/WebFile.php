<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\FlySystem;

use FSi\Component\Files;

final class WebFile implements Files\WebFile
{
    /**
     * @var string
     */
    private $fileSystemPrefix;

    /**
     * @var string
     */
    private $path;

    public function __construct(string $fileSystemPrefix, string $path)
    {
        $this->fileSystemPrefix = $fileSystemPrefix;
        $this->path = $path;
    }

    public function getFileSystemPrefix(): string
    {
        return $this->fileSystemPrefix;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
