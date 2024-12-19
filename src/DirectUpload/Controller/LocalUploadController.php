<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Controller;

use DateTimeImmutable;
use FSi\Component\Files\DirectUpload\Controller\DTO\LocalUploadDTO;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\Upload\PsrFilesHandler;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LocalUploadController
{
    public function __construct(
        private readonly FileManager $fileManager,
        private readonly PsrFilesHandler $psrFilesHandler,
        private readonly LocalUploadSigner $localUploadSigner,
        private readonly ClockInterface $clock,
        private readonly ResponseFactoryInterface $responseFactory
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $dto = LocalUploadDTO::fromRequestWithFiles($request, $this->psrFilesHandler);

        $expiresAt = (int) $request->getHeaderLine('x-expires-at');
        $signature = $request->getHeaderLine('x-signature');
        $dataToSign = [
            $dto->getFileSystemName(),
            $dto->getPath(),
            (string) $expiresAt
        ];

        if (false === $this->localUploadSigner->verify($dataToSign, $signature)) {
            return $this->responseFactory->createResponse(403);
        }

        if ($this->clock->now() > new DateTimeImmutable("@{$expiresAt}")) {
            return $this->responseFactory->createResponse(403);
        }

        $this->fileManager->copyFromStream(
            $dto->getFile()->getStream(),
            $dto->getFileSystemName(),
            $dto->getPath()
        );

        return $this->responseFactory->createResponse();
    }
}
