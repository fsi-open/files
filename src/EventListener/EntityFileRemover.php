<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\EventListener;

use FSi\Component\Files\FileManager;
use FSi\Component\Files\Integration\FlySystem\WebFile;
use function array_walk;

class EntityFileRemover
{
    /**
     * @var WebFile[]
     */
    private $filesToRemove = [];

    /**
     * @var FileManager
     */
    private $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    public function add(WebFile $file): void
    {
        $this->filesToRemove[] = $file;
    }

    public function flush(): void
    {
        array_walk($this->filesToRemove, function (WebFile $file): void {
            $this->fileManager->remove($file);
        });

        $this->filesToRemove = [];
    }
}
