<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem\DirectUpload;

use FSi\Component\Files\DirectUpload\Controller\LocalUploadSigner;
use FSi\Component\Files\DirectUpload\Controller\Response\Multipart;
use FSi\Component\Files\DirectUpload\Controller\Response\Params;
use FSi\Component\Files\DirectUpload\DirectUploadAdapter;
use FSi\Component\Files\DirectUpload\Event\WebFileDirectUpload;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

use function implode;

final class LocalAdapter implements DirectUploadAdapter
{
    public function __construct(
        private readonly UriFactoryInterface $uriFactory,
        private readonly ClockInterface $clock,
        private readonly LocalUploadSigner $localUploadSigner,
        private readonly string $localUploadPath,
        private readonly string $signatureExpiration = '+1 hour'
    ) {
    }

    public function prepare(WebFileDirectUpload $event): Params
    {
        $path = implode(
            '/',
            [
                $this->localUploadPath,
                $event->getWebFile()->getFileSystemName(),
                $event->getWebFile()->getPath()
            ]
        );

        $expiresAt = (string) $this->clock->now()->modify($this->signatureExpiration)->getTimestamp();

        $dataToSign = [
            $event->getWebFile()->getFileSystemName(),
            $event->getWebFile()->getPath(),
            $expiresAt
        ];

        return new Params(
            $this->uriFactory->createUri()->withPath($path),
            $event->getWebFile()->getPath(),
            $event->getOptions() + [
                'x-expires-at' => $expiresAt,
                'x-signature' => $this->localUploadSigner->sign($dataToSign),
            ]
        );
    }

    public function multipart(WebFileDirectUpload $event): Multipart
    {
        throw new RuntimeException('Not implemented');
    }

    public function parts(string $uploadId, string $key): array
    {
        throw new RuntimeException('Not implemented');
    }

    public function part(string $uploadId, string $key, int $number): UriInterface
    {
        throw new RuntimeException('Not implemented');
    }

    public function complete(string $uploadId, string $key, array $parts): void
    {
        throw new RuntimeException('Not implemented');
    }

    public function abort(string $uploadId, string $key): void
    {
        throw new RuntimeException('Not implemented');
    }
}
