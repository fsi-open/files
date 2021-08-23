<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Entity;

use FSi\Component\Files\WebFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UnionTestEntity
{
    private ?WebFile $file = null;
    private ?string $filePath = null;
    private int|float|string|null $scalarUnionType = null;
    private WebFile|UploadedFile|null $fileUnionType = null;
    private int|float|string $scalarUnionType1;
    private WebFile|UploadedFile $fileUnionType1;
}
