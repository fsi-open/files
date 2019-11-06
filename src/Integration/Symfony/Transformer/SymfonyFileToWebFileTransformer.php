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
        return $this->fileFactory->create(
            $file->getPath(),
            $file->getClientOriginalName(),
            $file->getMimeType(),
            $file->getSize(),
            $file->getError()
        );
    }
}
