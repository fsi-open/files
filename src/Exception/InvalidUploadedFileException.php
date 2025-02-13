<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Exception;

final class InvalidUploadedFileException extends FilesException
{
    public function __construct(string $field)
    {
        parent::__construct("Uploaded file does not have required {$field}");
    }
}
