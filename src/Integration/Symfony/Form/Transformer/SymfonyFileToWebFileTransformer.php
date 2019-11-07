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
use GuzzleHttp\Psr7\Stream;
use RuntimeException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SymfonyFileToWebFileTransformer implements FormFileTransformer
{
    /**
     * @var FileFactory
     */
    private $fileFactory;

    public function __construct(FileFactory $fileFactory)
    {
        $this->fileFactory = $fileFactory;
    }

    public function getFormEvent(): string
    {
        return FormEvents::PRE_SUBMIT;
    }

    public function transform(FormEvent $event): void
    {
        $file = $event->getData();
        if (false === $file instanceof UploadedFile) {
            return;
        }

        if (null === $file->getClientOriginalName()) {
            throw new RuntimeException('No filename!');
        }

        $filename = $file->getClientOriginalName();
        if (false === is_string($file->getMimeType())) {
            throw new RuntimeException("No mime type for file \"{$filename}\"!");
        }

        $stream = fopen($file->getPathname(), 'r');
        if (false === $stream) {
            throw new RuntimeException("Unable to read file \"{$filename}\"");
        }

        $event->setData(
            $this->fileFactory->create(
                new Stream($stream),
                $file->getClientOriginalName(),
                $file->getMimeType(),
                $file->getSize(),
                $file->getError()
            )
        );
    }
}
