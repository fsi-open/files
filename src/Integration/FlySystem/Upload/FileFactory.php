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
use Psr\Http\Message\StreamInterface;

final class FileFactory implements Files\Upload\FileFactory
{
    public function create(
        StreamInterface $stream,
        string $originalName,
        string $type,
        int $size,
        ?int $error
    ): Files\UploadedWebFile {
        return new FlySystem\UploadedWebFile(
            $stream,
            $originalName,
            $type,
            $size,
            $error
        );
    }
}
