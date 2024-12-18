<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Event;

use FSi\Component\Files\TemporaryWebFile;

final class WebFileDirectTemporaryUpload implements WebFileDirectUpload
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly TemporaryWebFile $webFile,
        private array $options = []
    ) {
    }

    public function getWebFile(): TemporaryWebFile
    {
        return $this->webFile;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
