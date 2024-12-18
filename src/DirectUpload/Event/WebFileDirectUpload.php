<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Event;

use FSi\Component\Files\WebFile;

interface WebFileDirectUpload
{
    public function getWebFile(): WebFile;
    /**
     * @return array<string, string>
     */
    public function getOptions(): array;
    /**
     * @param array<string, string> $options
     */
    public function setOptions(array $options): void;
}
