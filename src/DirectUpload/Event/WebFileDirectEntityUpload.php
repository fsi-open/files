<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Event;

use FSi\Component\Files\DirectlyUploadedWebFile;
use FSi\Component\Files\FilePropertyConfiguration;

final class WebFileDirectEntityUpload implements WebFileDirectUpload
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly FilePropertyConfiguration $filePropertyConfiguration,
        private readonly DirectlyUploadedWebFile $webFile,
        private array $options = []
    ) {
    }

    public function getFilePropertyConfiguration(): FilePropertyConfiguration
    {
        return $this->filePropertyConfiguration;
    }

    public function getWebFile(): DirectlyUploadedWebFile
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
