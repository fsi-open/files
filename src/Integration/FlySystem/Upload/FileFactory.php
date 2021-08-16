<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem\Upload;

use finfo;
use FSi\Component\Files;
use FSi\Component\Files\Integration\FlySystem;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

use function explode;
use function basename;
use function file_exists;
use function is_string;
use function mb_strlen;
use function mime_content_type;
use function mb_strtolower;
use function reset;

use const FILEINFO_MIME_TYPE;
use const UPLOAD_ERR_OK;

final class FileFactory implements Files\Upload\FileFactory
{
    private const MIME_TYPE_DEFAULT = 'application/octet-stream';

    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private ?finfo $fileinfo;

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->fileinfo = null;
    }

    public function create(
        StreamInterface $stream,
        string $originalName,
        string $type,
        int $size,
        ?int $error
    ): Files\UploadedWebFile {
        return new FlySystem\UploadedWebFile(
            $stream,
            $originalName,
            $type,
            $size,
            $error
        );
    }

    public function createFromPath(string $path, ?string $targetName = null): Files\UploadedWebFile
    {
        if (false === file_exists($path)) {
            throw new RuntimeException("File at path \"{$path}\" does not exist.");
        }

        $stream = $this->streamFactory->createStreamFromFile($path);
        $size = $stream->getSize();
        if (null === $size) {
            throw new RuntimeException("Unable to read size of file at path \"{$path}\".");
        }

        $mimeType = mime_content_type($path);
        if (false === $mimeType) {
            throw new RuntimeException("Unable to read mime type from file at path \"{$path}\".");
        }

        return new FlySystem\UploadedWebFile(
            $stream,
            $targetName ?? basename($path),
            $mimeType,
            $size,
            UPLOAD_ERR_OK
        );
    }

    public function createFromUri(UriInterface $uri, ?string $targetName = null): Files\UploadedWebFile
    {
        $response = $this->client->sendRequest(
            $this->requestFactory->createRequest('GET', $uri)
        );

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(
                sprintf(
                    'Uri "%s" is not reachable (HTTP error "%s").',
                    (string) $uri,
                    $response->getStatusCode()
                )
            );
        }

        $stream = $response->getBody();
        $size = $stream->getSize();
        if (null === $size) {
            throw new RuntimeException(
                sprintf('Unable to file size of "%s".', (string) $uri)
            );
        }

        $mimeType = $this->getMimeTypeFromResponse($response, $stream);
        return $this->create(
            $stream,
            $targetName ?? $this->getFileNameFromUri($uri),
            $mimeType,
            $size,
            UPLOAD_ERR_OK
        );
    }

    private function getFileNameFromUri(UriInterface $uri): string
    {
        $path = $uri->getPath();
        if (0 === mb_strlen($path)) {
            throw new RuntimeException(
                sprintf('No path present in "%s".', (string) $uri)
            );
        }

        $name = basename($path);
        if (0 === mb_strlen($name)) {
            throw new RuntimeException("Unable to read file name from path \"{$path}\".");
        }

        return $name;
    }

    private function getMimeTypeFromResponse(ResponseInterface $response, StreamInterface $stream): string
    {
        $mimeType = $this->getMimeTypeFromHeader($response->getHeader('Content-Type'));
        if (null === $mimeType) {
            $buffer = $this->getFileInfo()->buffer($stream->getContents());
            $stream->rewind();
            $mimeType = true === is_string($buffer) ? $buffer : self::MIME_TYPE_DEFAULT;
        }

        return $mimeType;
    }

    /**
     * @param array<string> $header
     * @return string|null
     */
    private function getMimeTypeFromHeader(array $header): ?string
    {
        if (0 === count($header)) {
            return null;
        }

        // Handle possible charset option (Content-Type=text/html;charset=utf8)
        $headerContents = explode(';', reset($header));
        return mb_strtolower($headerContents[0]);
    }

    private function getFileInfo(): finfo
    {
        if (null === $this->fileinfo) {
            $this->fileinfo = new finfo(FILEINFO_MIME_TYPE);
        }

        return $this->fileinfo;
    }
}
