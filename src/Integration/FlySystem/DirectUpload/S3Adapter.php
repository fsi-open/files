<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem\DirectUpload;

use Aws\S3\S3ClientInterface;
use FSi\Component\Files\DirectUpload\Controller\Response\Multipart;
use FSi\Component\Files\DirectUpload\Controller\Response\Params;
use FSi\Component\Files\DirectUpload\Controller\Response\Part;
use FSi\Component\Files\DirectUpload\DirectUploadAdapter;
use FSi\Component\Files\DirectUpload\Event\WebFileDirectUpload;
use League\Flysystem\PathPrefixer;
use Psr\Http\Message\UriInterface;

use function array_map;

final class S3Adapter implements DirectUploadAdapter
{
    private S3ClientInterface $client;
    private string $bucket;
    private PathPrefixer $prefix;
    /**
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * @param S3ClientInterface $client
     * @param string $bucket
     * @param string $prefix
     * @param array<string, mixed> $options
     */
    public function __construct(
        S3ClientInterface $client,
        string $bucket,
        string $prefix = '',
        array $options = [],
    ) {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->prefix = new PathPrefixer($prefix);
        $this->options = $options;
    }

    public function prepare(WebFileDirectUpload $event): Params
    {
        $key = $this->prefix->prefixPath($event->getWebFile()->getPath());
        $cmd = $this->client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ] + $event->getOptions() + $this->options);

        $signedRequest = $this->client->createPresignedRequest($cmd, '+1 hour');

        return new Params($signedRequest->getUri(), $key, $signedRequest->getHeaders());
    }

    public function multipart(WebFileDirectUpload $event): Multipart
    {
        $key = $this->prefix->prefixPath($event->getWebFile()->getPath());
        $cmd = $this->client->getCommand(
            'CreateMultipartUpload',
            ['Bucket' => $this->bucket, 'Key' => $key] + $event->getOptions()
        );
        $result = $this->client->execute($cmd);

        return new Multipart($result->get('UploadId'), $key);
    }

    public function parts(string $uploadId, string $key): array
    {
        $cmd = $this->client->getCommand('listParts', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);
        $result = $this->client->execute($cmd);

        return array_map(static fn(array $partData): Part => Part::fromArray($partData), $result->get('Parts'));
    }

    public function part(string $uploadId, string $key, int $number): UriInterface
    {
        $cmd = $this->client->getCommand('UploadPart', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $number,
        ]);

        return $this->client->createPresignedRequest($cmd, '+1 hour')->getUri();
    }

    public function complete(string $uploadId, string $key, array $parts): void
    {
        $cmd = $this->client->getCommand('CompleteMultipartUpload', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => array_map(static fn(Part $part): array => (array) $part, $parts),
            ],
        ]);
        $this->client->execute($cmd);
    }

    public function abort(string $uploadId, string $key): void
    {
        $cmd = $this->client->getCommand('AbortMultipartUpload', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);
        $this->client->execute($cmd);
    }

    public function getClient(): S3ClientInterface
    {
        return $this->client;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getPrefix(): PathPrefixer
    {
        return $this->prefix;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
