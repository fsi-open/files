<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Transformer;

use FSi\Component\Files\Upload\FileFactory;
use FSi\Component\Files\UploadedWebFile;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class PsrFileToWebFileTransformer
{
    /**
     * @var FileFactory
     */
    private $fileFactory;

    public function __construct(FileFactory $fileFactory)
    {
        $this->fileFactory = $fileFactory;
    }

    public function transform(UploadedFileInterface $file): UploadedWebFile
    {
        if (null === $file->getClientFilename()) {
            throw new RuntimeException('No filename!');
        }

        $filename = $file->getClientFilename();
        if (false === is_string($file->getClientMediaType())) {
            throw new RuntimeException("No media type for file \"{$filename}\"!");
        }

        if (null === $file->getSize()) {
            throw new RuntimeException("No size for file \"{$filename}\"!");
        }

        return $this->fileFactory->create(
            $file->getStream(),
            $filename,
            $file->getClientMediaType(),
            $file->getSize(),
            $file->getError()
        );
    }
}
