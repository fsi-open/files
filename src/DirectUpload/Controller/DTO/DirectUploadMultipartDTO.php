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

class DirectUploadMultipartDTO extends DirectUploadDTO
{
    public function getKey(): string
    {
        Assertion::keyExists($this->body, 'key');

        return $this->body['key'];
    }

    public function getUploadId(): string
    {
        Assertion::keyExists($this->body, 'uploadId');

        return $this->body['uploadId'];
    }
}
