<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files;

use Assert\Assertion;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use function array_key_exists;
use function sprintf;

final class FileUrlResolver
{
    /**
     * @var array<string, UrlAdapter>
     */
    private $adapters;

    public function __construct(array $adapters)
    {
        Assertion::allIsInstanceOf($adapters, UrlAdapter::class);
        $this->adapters = $adapters;
    }

    public function resolve(WebFile $file): UriInterface
    {
        if (false === array_key_exists($file->getFileSystemName(), $this->adapters)) {
            throw new RuntimeException(sprintf(
                'Unable to resolve url for file "%s" of class "%s" from filesystem "%s"',
                $file->getPath(),
                get_class($file),
                $file->getFileSystemName()
            ));
        }

        return $this->adapters[$file->getFileSystemName()]->url($file);
    }
}
