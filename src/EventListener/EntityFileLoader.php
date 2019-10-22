<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\EventListener;

use Assert\Assertion;
use FSi\Component\Files\FlySystem\FilePropertyConfiguration;
use FSi\Component\Files\FlySystem\WebFile;

class EntityFileLoader
{
    public function fromEntity(FilePropertyConfiguration $configuration, object $entity): ?WebFile
    {
        Assertion::isInstanceOf($entity, $configuration->getEntityClass());

        $path = $configuration->getPathPropertyReflection()->getValue($entity);
        if (null === $path) {
            return null;
        }

        return new WebFile($configuration->getFileSystemPrefix(), $path);
    }
}
