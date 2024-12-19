<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Controller\Response;

use Psr\Http\Message\UriInterface;

final class Params
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        public readonly UriInterface $url,
        public readonly string $fileSystem,
        public readonly string $key,
        public readonly array $headers
    ) {
    }
}
