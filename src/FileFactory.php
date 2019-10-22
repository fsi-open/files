<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files;

use Psr\Http\Message\UploadedFileInterface;

interface FileFactory
{
    public function createFromUploadedFile(?UploadedFileInterface $file): ?WebFile;
    public function createFromContents(string $filename, string $contents): WebFile;
}
