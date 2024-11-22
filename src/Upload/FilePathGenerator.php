<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Upload;

use Ramsey\Uuid\Uuid;

use function sprintf;
use function str_replace;

final class FilePathGenerator
{
    public static function generate(string $filename, ?string $prefix): string
    {
        // Mitigate filesystem limits for maximum number of subdirectories
        $uuid = str_replace('-', '', Uuid::uuid4()->toString());
        $splitUuid = sprintf(
            '%s/%s/%s/%s',
            mb_substr($uuid, 0, 3),
            mb_substr($uuid, 3, 3),
            mb_substr($uuid, 6, 3),
            mb_substr($uuid, 9)
        );

        return sprintf('%s%s/%s', (null !== $prefix) ? "$prefix/" : '', $splitUuid, $filename);
    }
}
