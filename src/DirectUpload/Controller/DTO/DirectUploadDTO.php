<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Controller\DTO;

use Assert\Assertion;

abstract class DirectUploadDTO extends PostRequestDTO
{
    public function getTarget(): ?string
    {
        return $this->body['target'] ?? null;
    }

    public function getFileSystemName(): string
    {
        Assertion::keyExists($this->body, 'fileSystemName');

        return $this->body['fileSystemName'];
    }

    public function getFileSystemPrefix(): string
    {
        Assertion::keyExists($this->body, 'fileSystemPrefix');

        return $this->body['fileSystemPrefix'];
    }
}
