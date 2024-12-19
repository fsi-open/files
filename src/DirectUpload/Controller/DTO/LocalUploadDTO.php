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
use FSi\Component\Files\UploadedWebFile;

final class LocalUploadDTO extends PostRequestDTO
{
    public function getFileSystemName(): string
    {
        Assertion::keyExists($this->attributes, 'filesystem');
        Assertion::notNull($this->attributes['filesystem']);

        return $this->attributes['filesystem'];
    }

    public function getPath(): string
    {
        Assertion::keyExists($this->attributes, 'path');
        Assertion::notNull($this->attributes['path']);

        return $this->attributes['path'];
    }

    public function getFile(): UploadedWebFile
    {
        Assertion::keyExists($this->files, 'file');
        Assertion::isInstanceOf($this->files['file'], UploadedWebFile::class);

        return $this->files['file'];
    }
}
