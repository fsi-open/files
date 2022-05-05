<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form\Transformer;

use FSi\Component\Files\Upload\FileFactory;
use FSi\Component\Files\UploadedWebFile;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Symfony\Component\Form\FormEvent;

use function is_string;

use const UPLOAD_ERR_OK;

final class PsrFileToWebFileTransformer implements FormFileTransformer
{
    private FileFactory $fileFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(FileFactory $fileFactory, StreamFactoryInterface $streamFactory)
    {
        $this->fileFactory = $fileFactory;
        $this->streamFactory = $streamFactory;
    }

    public function __invoke(FormEvent $event): void
    {
        $file = $event->getData();
        if (false === $file instanceof UploadedFileInterface) {
            return;
        }

        $result = UPLOAD_ERR_OK === $file->getError()
            ? $this->transformFile($file)
            : $this->createEmptyFileWithError($file->getError())
        ;

        $event->setData($result);
    }

    private function transformFile(UploadedFileInterface $file): UploadedWebFile
    {
        $filename = $file->getClientFilename();
        if (null === $filename) {
            throw new RuntimeException('No filename!');
        }

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

    private function createEmptyFileWithError(int $error): UploadedWebFile
    {
        return $this->fileFactory->create(
            $this->streamFactory->createStream(),
            '',
            '',
            0,
            $error
        );
    }
}
