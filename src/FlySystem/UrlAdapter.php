<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\FlySystem;

use FSi\Component\Files;
use Psr\Http\Message\UriInterface;

interface UrlAdapter
{
    public function supports(Files\WebFile $file): bool;
    public function url(Files\WebFile $file): UriInterface;
}
