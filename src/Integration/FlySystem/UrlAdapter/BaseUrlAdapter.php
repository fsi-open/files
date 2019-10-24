<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem\UrlAdapter;

use FSi\Component\Files;
use FSi\Component\Files\Integration\FlySystem;
use FSi\Component\Files\UrlAdapter;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

final class BaseUrlAdapter implements UrlAdapter
{
    /**
     * @var string
     */
    private $fileSystemName;

    /**
     * @var UriInterface
     */
    private $baseUrl;

    public function __construct(UriFactoryInterface $uriFactory, string $fileSystemName, string $baseUrl)
    {
        $this->fileSystemName = $fileSystemName;
        $this->baseUrl = $uriFactory->createUri($baseUrl);
    }

    public function supports(Files\WebFile $file): bool
    {
        return true === $file instanceof FlySystem\WebFile && $this->fileSystemName === $file->getFileSystemName();
    }

    public function url(Files\WebFile $file): UriInterface
    {
        return UriResolver::resolve($this->baseUrl, new Uri($file->getPath()));
    }
}
