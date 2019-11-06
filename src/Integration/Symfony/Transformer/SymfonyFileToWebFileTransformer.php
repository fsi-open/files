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
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\StreamWrapper;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SymfonyFileToWebFileTransformer
{
    /**
     * @var FileFactory
     */
    private $fileFactory;

    public function __construct(FileFactory $fileFactory)
    {
        $this->fileFactory = $fileFactory;
    }

    public function transform(UploadedFile $file): UploadedWebFile
    {
        if (null === $file->getClientOriginalName()) {
            throw new RuntimeException('No filename!');
        }

        $filename = $file->getClientOriginalName();
        if (false === is_string($file->getMimeType())) {
            throw new RuntimeException("No mime type for file \"{$filename}\"!");
        }

        if (0 === $file->getSize()) {
            throw new RuntimeException("Empty file \"{$filename}\"!");
        }

        $stream = fopen($file->getPathname(), 'r');
        if (false === $stream) {
            throw new RuntimeException("Unable to read file \"{$filename}\"");
        }

        return $this->fileFactory->create(
            new Stream($stream),
            $file->getClientOriginalName(),
            $file->getMimeType(),
            $file->getSize(),
            $file->getError()
        );
    }
}
