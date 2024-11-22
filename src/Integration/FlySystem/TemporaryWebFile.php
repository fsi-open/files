<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\FlySystem;

use FSi\Component\Files;

final class TemporaryWebFile implements Files\TemporaryWebFile
{
    use WebFileImplementation;
}
