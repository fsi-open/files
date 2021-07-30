<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\App\Entity;

use FSi\Component\Files\WebFile;

class TwiceEmbeddedFile
{
    /**
     * @var WebFile|null
     */
    public $file;

    /**
     * @var string
     */
    public $filePath;
}
