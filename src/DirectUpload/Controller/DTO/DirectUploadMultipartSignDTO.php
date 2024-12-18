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

class DirectUploadMultipartSignDTO extends DirectUploadMultipartDTO
{
    public function getPartNumber(): int
    {
        Assertion::keyExists($this->body, 'partNumber');

        return (int) $this->body['partNumber'];
    }
}
