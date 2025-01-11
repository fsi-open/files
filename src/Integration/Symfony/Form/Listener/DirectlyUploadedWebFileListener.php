<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form\Listener;

use FSi\Component\Files\FileManager;
use FSi\Component\Files\Integration\Symfony\Form\WebFileType;
use FSi\Component\Files\Upload\FileFactory;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormEvent;

use function array_key_exists;
use function is_array;
use function is_string;

final class DirectlyUploadedWebFileListener
{
    private FileManager $fileManager;
    private FileFactory $fileFactory;
    private bool $temporary;
    private string $fileSystemName;

    public function __construct(
        FileManager $fileManager,
        FileFactory $fileFactory,
        bool $temporary,
        string $fileSystemName,
    ) {
        $this->fileManager = $fileManager;
        $this->fileFactory = $fileFactory;
        $this->temporary = $temporary;
        $this->fileSystemName = $fileSystemName;
    }

    public function __invoke(FormEvent $event): void
    {
        $data = $event->getData();
        if (false === is_array($data)) {
            return;
        }

        if (
            true === array_key_exists(WebFileType::PATH_FIELD, $data)
            && null !== ($data[WebFileType::PATH_FIELD] ?: null)
            && true === is_string($data[WebFileType::PATH_FIELD])
        ) {
            if (true === $this->temporary) {
                $webFile = $this->fileFactory->createTemporary(
                    $this->fileSystemName,
                    $data[WebFileType::PATH_FIELD]
                );
            } else {
                $webFile = $this->fileFactory->createDirectlyUploaded(
                    $this->fileSystemName,
                    $data[WebFileType::PATH_FIELD]
                );
            }

            if (false === $this->fileManager->exists($webFile)) {
                throw new TransformationFailedException('File was not uploaded', 0, null, 'No file was uploaded.');
            }

            $data[WebFileType::FILE_FIELD] = $webFile;

            $event->setData($data);
        }
    }
}
