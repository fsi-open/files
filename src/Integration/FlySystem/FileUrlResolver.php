<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem;

use Assert\Assertion;
use FSi\Component\Files;
use FSi\Component\Files\UrlAdapter;
use Psr\Http\Message\UriInterface;
use function array_reduce;
use function sprintf;

final class FileUrlResolver implements Files\FileUrlResolver
{
    /**
     * @var UrlAdapter[]
     */
    private $adapters;

    public function __construct(array $adapters)
    {
        Assertion::allIsInstanceOf($adapters, UrlAdapter::class);
        $this->adapters = $adapters;
    }

    public function resolve(Files\WebFile $file): UriInterface
    {
        Assertion::isInstanceOf($file, WebFile::class);

        $url = $this->resolveFlySystemFileToUrl($file);
        Assertion::notNull($url, sprintf(
            'Unable to find Url resolver for file "%s" from filesystem "%s"',
            $file->getPath(),
            $file->getFileSystemName()
        ));

        return $url;
    }

    private function resolveFlySystemFileToUrl(WebFile $file): ?UriInterface
    {
        return array_reduce(
            $this->adapters,
            function (?UriInterface $accumulator, UrlAdapter $adapter) use ($file): ?UriInterface {
                if (null !== $accumulator) {
                    return $accumulator;
                }

                if (true === $adapter->supports($file)) {
                    $accumulator = $adapter->url($file);
                }

                return $accumulator;
            }
        );
    }
}
