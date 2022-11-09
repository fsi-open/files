<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Exception;

use Exception;
use FSi\Component\Files\WebFile;

use function sprintf;

final class FileNotFoundException extends Exception
{
    public static function forFile(WebFile $webFile): self
    {
        return new self(
            sprintf(
                'File for filesystem "%s" not found at path "%s".',
                $webFile->getFileSystemName(),
                $webFile->getPath()
            )
        );
    }
}
