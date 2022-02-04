<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\AmazonS3\UrlAdapter;

use Aws\S3\S3ClientInterface;
use DateTime;
use FSi\Component\Files\UrlAdapter;
use FSi\Component\Files\WebFile;
use Psr\Http\Message\UriInterface;

final class S3PrivateUrlAdapter implements UrlAdapter
{
    private S3ClientInterface $s3Client;
    private string $s3Bucket;
    /**
     * @var int|string|DateTime
     */
    private $expiresIn;

    /**
     * @param S3ClientInterface $s3Client
     * @param string $s3Bucket
     * @param int|string|DateTime $expiresIn
     */
    public function __construct(S3ClientInterface $s3Client, string $s3Bucket, $expiresIn)
    {
        $this->s3Client = $s3Client;
        $this->s3Bucket = $s3Bucket;
        $this->expiresIn = $expiresIn;
    }

    public function url(WebFile $file): UriInterface
    {
        $cmd = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $this->s3Bucket,
            'Key' => $file->getPath()
        ]);

        return $this->s3Client->createPresignedRequest($cmd, $this->expiresIn)->getUri();
    }
}
