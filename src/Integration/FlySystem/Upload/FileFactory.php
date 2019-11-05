<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem\Upload;

use FSi\Component\Files;
use FSi\Component\Files\Integration\FlySystem;

final class FileFactory implements Files\Upload\FileFactory
{
    public function create(
        string $path,
        string $originalName,
        string $type,
        int $size,
        ?int $error
    ): Files\UploadedWebFile {
        return new FlySystem\UploadedWebFile(
            'php_temp',
            $path,
            $originalName,
            $type,
            $size,
            $error
        );
    }
}
