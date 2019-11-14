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
use FSi\Component\Files\UrlAdapter;
use FSi\Component\Files\WebFile;
use Psr\Http\Message\UriInterface;

final class S3PrivateUrlAdapter implements UrlAdapter
{
    /**
     * @var S3ClientInterface
     */
    private $amazonClient;

    /**
     * @var string
     */
    private $amazonBucket;

    public function __construct(S3ClientInterface $amazonClient, string $amazonBucket)
    {
        $this->amazonClient = $amazonClient;
        $this->amazonBucket = $amazonBucket;
    }

    public function url(WebFile $file): UriInterface
    {
        $cmd = $this->amazonClient->getCommand('GetObject', [
            'Bucket' => $this->amazonBucket,
            'Key' => $file->getPath()
        ]);

        return $this->amazonClient->createPresignedRequest($cmd, '+1 hour')->getUri();
    }
}
