<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\UrlAdapter;

use FSi\Component\Files\UrlAdapter;
use FSi\Component\Files\WebFile;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

final class BaseUrlAdapter implements UrlAdapter
{
    /**
     * @var UriInterface
     */
    private $baseUrl;

    public function __construct(UriFactoryInterface $uriFactory, string $baseUrl)
    {
        $this->baseUrl = $uriFactory->createUri($baseUrl);
    }

    public function url(WebFile $file): UriInterface
    {
        return UriResolver::resolve($this->baseUrl, new Uri($file->getPath()));
    }
}
