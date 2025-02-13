<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Upload;

use FSi\Component\Files\Exception\InvalidUploadedFileException;
use FSi\Component\Files\UploadedWebFile;
use Psr\Http\Message\UploadedFileInterface;

final class PsrFilesHandler
{
    public function __construct(private readonly FileFactory $fileFactory)
    {
    }

    public function create(UploadedFileInterface $uploadedFile): UploadedWebFile
    {
        $originalName = $uploadedFile->getClientFilename();
        if (null === $originalName) {
            throw new InvalidUploadedFileException('original name');
        }

        $mediaType = $uploadedFile->getClientMediaType();
        if (null === $mediaType) {
            throw new InvalidUploadedFileException('content type');
        }

        $size = $uploadedFile->getSize();
        if (null === $size) {
            throw new InvalidUploadedFileException('size');
        }

        return $this->fileFactory->create(
            $uploadedFile->getStream(),
            $originalName,
            $mediaType,
            $size,
            $uploadedFile->getError()
        );
    }
}
