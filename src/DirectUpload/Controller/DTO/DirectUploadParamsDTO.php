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

class DirectUploadParamsDTO extends DirectUploadDTO
{
    public function getFilename(): string
    {
        Assertion::keyExists($this->body, 'filename');

        return $this->body['filename'];
    }

    public function getContentType(): string
    {
        Assertion::keyExists($this->body, 'contentType');

        return $this->body['contentType'];
    }
}
