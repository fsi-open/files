<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Controller;

use FSi\Component\Files\DirectlyUploadedWebFile;
use FSi\Component\Files\DirectUpload\AdapterRegistry;
use FSi\Component\Files\DirectUpload\Controller\DTO\DirectUploadDTO;
use FSi\Component\Files\DirectUpload\Controller\DTO\DirectUploadMultipartDTO;
use FSi\Component\Files\DirectUpload\Controller\DTO\DirectUploadMultipartSignDTO;
use FSi\Component\Files\DirectUpload\Controller\DTO\DirectUploadParamsDTO;
use FSi\Component\Files\DirectUpload\Controller\Response\Part;
use FSi\Component\Files\DirectUpload\DirectUploadAdapter;
use FSi\Component\Files\DirectUpload\DirectUploadTargetEncryptor;
use FSi\Component\Files\DirectUpload\Event\WebFileDirectEntityUpload;
use FSi\Component\Files\DirectUpload\Event\WebFileDirectTemporaryUpload;
use FSi\Component\Files\DirectUpload\Event\WebFileDirectUpload;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FileUrlResolver;
use FSi\Component\Files\TemporaryWebFile;
use FSi\Component\Files\Upload\FileFactory;
use FSi\Component\Files\Upload\FilePathGenerator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function array_map;
use function array_merge;
use function json_encode;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;

final class DirectUploadController
{
    private const JSON_ENCODE_FLAGS = JSON_HEX_TAG
        | JSON_HEX_APOS
        | JSON_HEX_AMP
        | JSON_HEX_QUOT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly DirectUploadTargetEncryptor $directUploadTargetEncryptor,
        private readonly FileFactory $fileFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AdapterRegistry $adapterRegistry,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {
    }

    public function params(ServerRequestInterface $request): ResponseInterface
    {
        $dto = DirectUploadParamsDTO::fromRequest($request);

        $event = $this->dispatchDirectUploadEvent($dto);
        $params = $this->getAdapter($dto)->prepare($event);
        $publicUri = $this->fileUrlResolver->tryResolve($event->getWebFile());

        return $this->createJsonResponse([
            'url' => (string) $params->url,
            'fileSystem' => $params->fileSystem,
            'key' => $params->key,
            'headers' => $params->headers,
            'publicUrl' => (null !== $publicUri) ? (string) $publicUri : null,
        ]);
    }

    public function createMultipart(ServerRequestInterface $request): ResponseInterface
    {
        $dto = DirectUploadParamsDTO::fromRequest($request);

        $event = $this->dispatchDirectUploadEvent($dto);
        $multipart = $this->getAdapter($dto)->multipart($event);
        $publicUri = $this->fileUrlResolver->tryResolve($event->getWebFile());

        return $this->createJsonResponse([
            'uploadId' => $multipart->uploadId,
            'fileSystem' => $multipart->fileSystem,
            'key' => $multipart->key,
            'publicUrl' => (null !== $publicUri) ? (string) $publicUri : null,
        ]);
    }

    public function listParts(ServerRequestInterface $request): ResponseInterface
    {
        $dto = DirectUploadMultipartDTO::fromRequest($request);

        return $this->createJsonResponse($this->getAdapter($dto)->parts($dto->getUploadId(), $dto->getKey()));
    }

    public function signPart(ServerRequestInterface $request): ResponseInterface
    {
        $dto = DirectUploadMultipartSignDTO::fromRequest($request);

        return $this->createJsonResponse([
            'url' => (string) $this->getAdapter($dto)->part($dto->getUploadId(), $dto->getKey(), $dto->getPartNumber()),
        ]);
    }

    public function completeMultipart(ServerRequestInterface $request): ResponseInterface
    {
        $dto = DirectUploadMultipartDTO::fromRequest($request);

        $this->getAdapter($dto)->complete(
            $dto->getUploadId(),
            $dto->getKey(),
            array_map(static fn(array $partData): Part => Part::fromArray($partData), $dto->body['parts'] ?? [])
        );

        return $this->createJsonResponse([]);
    }

    public function abortMultipart(ServerRequestInterface $request): ResponseInterface
    {
        $dto = DirectUploadMultipartDTO::fromRequest($request);

        $this->getAdapter($dto)->abort($dto->getUploadId(), $dto->getKey());

        return $this->createJsonResponse([]);
    }

    private function dispatchDirectUploadEvent(DirectUploadParamsDTO $dto): WebFileDirectUpload
    {
        $options = ['ContentType' => $dto->getContentType()];
        $target = $dto->getTarget();
        if (null !== $target) {
            $configuration = $this->directUploadTargetEncryptor->decrypt($target);
            $webFile = $this->createDirectlyUploadedWebFile($configuration, $dto->getFilename());
            $event = new WebFileDirectEntityUpload($configuration, $webFile, $options);
        } else {
            $webFile = $this->createTemporaryWebFile($dto);
            $event = new WebFileDirectTemporaryUpload($webFile, $options);
        }

        $this->eventDispatcher->dispatch($event);

        return $event;
    }

    private function createDirectlyUploadedWebFile(
        FilePropertyConfiguration $configuration,
        string $filename
    ): DirectlyUploadedWebFile {
        $filesystemName = $configuration->getFileSystemName();
        $path = FilePathGenerator::generate($filename, $configuration->getPathPrefix());

        return $this->fileFactory->createDirectlyUploaded($filesystemName, $path);
    }

    private function createTemporaryWebFile(DirectUploadParamsDTO $dto): TemporaryWebFile
    {
        $filesystemName = $dto->getFileSystemName();
        $filesystemPrefix = $dto->getFileSystemPrefix();
        $path = FilePathGenerator::generate($dto->getFilename(), $filesystemPrefix);

        return $this->fileFactory->createTemporary($filesystemName, $path);
    }

    private function getAdapter(DirectUploadDTO $dto): DirectUploadAdapter
    {
        return $this->adapterRegistry->getAdapter($this->getFileSystemName($dto));
    }

    private function getFileSystemName(DirectUploadDTO $dto): string
    {
        $target = $dto->getTarget();
        if (null !== $target) {
            return $this->directUploadTargetEncryptor->decrypt($target)->getFileSystemName();
        }

        return $dto->getFileSystemName();
    }

    /**
     * @param array<mixed> $data
     * @return ResponseInterface
     */
    private function createJsonResponse(array $data): ResponseInterface
    {
        $responseBody = $this->streamFactory->createStream(json_encode($data, self::JSON_ENCODE_FLAGS));

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withBody($responseBody);
    }
}
