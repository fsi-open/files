<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\FlySystem;

use FSi\Component\Files;
use FSi\Component\Files\FlySystem;
use GuzzleHttp\Psr7\StreamWrapper;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;
use const UPLOAD_ERR_OK;
use function sprintf;

final class FileFactory implements Files\FileFactory
{
    /**
     * @var FileManager
     */
    private $fileManager;

    /**
     * @var string
     */
    private $fileSystemPrefix;

    public function __construct(FileManager $fileManager, string $fileSystemPrefix)
    {
        $this->fileManager = $fileManager;
        $this->fileSystemPrefix = $fileSystemPrefix;
    }

    public function createFromUploadedFile(?UploadedFileInterface $file): ?Files\WebFile
    {
        if (null === $file || UPLOAD_ERR_OK !== $file->getError()) {
            return null;
        }

        $clientFilename = $file->getClientFilename();
        if (null === $clientFilename) {
            return null;
        }

        $path = $this->filenameToPath($clientFilename);
        $this->fileManager->writeStream(
            $this->fileSystemPrefix,
            $path,
            StreamWrapper::getResource($file->getStream())
        );

        return new FlySystem\WebFile($this->fileSystemPrefix, $path);
    }

    public function createFromContents(string $filename, string $contents): Files\WebFile
    {
        $path = $this->filenameToPath($filename);
        $this->fileManager->create($this->fileSystemPrefix, $path, $contents);

        return new FlySystem\WebFile($this->fileSystemPrefix, $path);
    }

    private function filenameToPath(string $filename): string
    {
        return sprintf('%s/%s', Uuid::uuid4()->toString(), $filename);
    }
}
