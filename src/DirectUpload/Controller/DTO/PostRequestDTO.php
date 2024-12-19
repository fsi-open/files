<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Controller\DTO;

use FSi\Component\Files\Upload\PsrFilesHandler;
use FSi\Component\Files\UploadedWebFile;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

use function array_map;
use function is_array;

abstract class PostRequestDTO
{
    /**
     * @var array<string, mixed>
     */
    public array $body;
    /**
     * @var array<string, string|null>
     */
    public array $attributes;
    /**
     * @var array<string, UploadedWebFile|array<UploadedWebFile>>
     */
    public array $files;

    public static function fromRequest(ServerRequestInterface $request): static
    {
        $instance = new static();
        $parsedBody = $request->getParsedBody();
        $instance->body = (null !== $parsedBody) ? ((array) $parsedBody) : [];
        $instance->attributes = $request->getAttributes();

        return $instance;
    }

    public static function fromRequestWithFiles(
        ServerRequestInterface $request,
        PsrFilesHandler $psrFilesHandler
    ): static {
        $instance = self::fromRequest($request);
        $instance->files = array_map(
            static fn(UploadedFileInterface|array $fileItem): UploadedWebFile|array
            => self::createUploadedWebFile($fileItem, $psrFilesHandler),
            $request->getUploadedFiles()
        );

        return $instance;
    }

    /**
     * @param UploadedFileInterface|array<mixed> $file
     * @param PsrFilesHandler $psrFilesHandler
     * @return UploadedWebFile|array<mixed>
     */
    private static function createUploadedWebFile(
        UploadedFileInterface|array $file,
        PsrFilesHandler $psrFilesHandler
    ): UploadedWebFile|array {
        if (false === is_array($file)) {
            return $psrFilesHandler->create($file);
        }

        return array_map(
            static fn(UploadedFileInterface|array $fileItem): UploadedWebFile|array
                => self::createUploadedWebFile($fileItem, $psrFilesHandler),
            $file
        );
    }

    final private function __construct()
    {
    }
}
