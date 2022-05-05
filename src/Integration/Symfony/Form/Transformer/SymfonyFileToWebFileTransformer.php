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
use RuntimeException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function is_string;

use const UPLOAD_ERR_OK;

final class SymfonyFileToWebFileTransformer implements FormFileTransformer
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
        if (false === $file instanceof UploadedFile) {
            return;
        }

        $result = UPLOAD_ERR_OK === $file->getError()
            ? $this->transformFile($file)
            : $this->createEmptyFileWithError($file->getError())
        ;

        $event->setData($result);
    }

    private function transformFile(UploadedFile $file): UploadedWebFile
    {
        $filename = $file->getClientOriginalName();
        if (null === $filename) {
            throw new RuntimeException('No filename!');
        }

        if (false === is_string($file->getMimeType())) {
            throw new RuntimeException("No mime type for file \"{$filename}\"!");
        }

        return $this->fileFactory->create(
            $this->streamFactory->createStreamFromFile($file->getPathname()),
            $filename,
            $file->getMimeType(),
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
