<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Entity\Event;

use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\WebFile;

trait WebFileEvent
{
    private WebFile $file;
    private FilePropertyConfiguration $configuration;
    private object $entity;

    public function __construct(FilePropertyConfiguration $configuration, object $entity, WebFile $file)
    {
        $this->configuration = $configuration;
        $this->entity = $entity;
        $this->file = $file;
    }

    public function getConfiguration(): FilePropertyConfiguration
    {
        return $this->configuration;
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getFile(): WebFile
    {
        return $this->file;
    }
}
