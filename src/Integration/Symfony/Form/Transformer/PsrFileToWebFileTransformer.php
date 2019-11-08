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
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Symfony\Component\Form\FormEvent;

final class PsrFileToWebFileTransformer implements FormFileTransformer
{
    /**
     * @var FileFactory
     */
    private $fileFactory;

    public function __construct(FileFactory $fileFactory)
    {
        $this->fileFactory = $fileFactory;
    }

    public function __invoke(FormEvent $event): void
    {
        $file = $event->getData();
        if (false === $file instanceof UploadedFileInterface) {
            return;
        }

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

        $event->setData(
            $this->fileFactory->create(
                $file->getStream(),
                $filename,
                $file->getClientMediaType(),
                $file->getSize(),
                $file->getError()
            )
        );
    }
}
