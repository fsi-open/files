<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use function array_keys;
use function ctype_digit;
use function implode;
use function preg_match;

/**
 * This is a copy of Symfony File constraint, adjusted for the slight differences
 * between implementation of file upload.
 *
 * @see \Symfony\Component\Validator\Constraints\File
 */
class UploadedWebFile extends Constraint
{
    public const NOT_FOUND_ERROR = 'd2a3fb6e-7ddc-4210-8fbf-2ab345ce1998';
    public const NOT_READABLE_ERROR = 'c20c92a4-5bfa-4202-9477-28e800e0f6ff';
    public const EMPTY_ERROR = '5d743385-9775-4aa5-8ff5-495fb1e60137';
    public const TOO_LARGE_ERROR = 'df8637af-d466-48c6-a59d-e7126250a654';
    public const INVALID_MIME_TYPE_ERROR = '744f00bc-4389-4c74-92de-9a43cde55534';

    /**
     * @var bool|null
     */
    public $binaryFormat;

    /**
     * @var array<string>|string|null
     */
    public $mimeTypes = [];

    /**
     * @var string
     */
    public $notFoundMessage = 'The file could not be found.';

    /**
     * @var string
     */
    public $notReadableMessage = 'The file is not readable.';

    /**
     * @var string
     */
    public $maxSizeMessage = 'The file is too large ({{ size }} {{ suffix }}).'
        . ' Allowed maximum size is {{ limit }} {{ suffix }}.'
    ;

    /**
     * @var string
     */
    public $mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.';

    /**
     * @var string
     */
    public $disallowEmptyMessage = 'An empty file is not allowed.';

    /**
     * @var string
     */
    public $uploadIniSizeErrorMessage = 'The file is too large. Allowed maximum size is {{ limit }} {{ suffix }}.';

    /**
     * @var string
     */
    public $uploadFormSizeErrorMessage = 'The file is too large.';

    /**
     * @var string
     */
    public $uploadPartialErrorMessage = 'The file was only partially uploaded.';

    /**
     * @var string
     */
    public $uploadNoFileErrorMessage = 'No file was uploaded.';

    /**
     * @var string
     */
    public $uploadNoTmpDirErrorMessage = 'No temporary folder was configured in php.ini.';

    /**
     * @var string
     */
    public $uploadCantWriteErrorMessage = 'Cannot write temporary file to disk.';

    /**
     * @var string
     */
    public $uploadExtensionErrorMessage = 'A PHP extension caused the upload to fail.';

    /**
     * @var string
     */
    public $uploadErrorMessage = 'The file could not be uploaded.';

    /**
     * @var int|null
     */
    public $maxSize;

    public function __construct($options = null)
    {
        parent::__construct($options);

        if (null !== $this->maxSize) {
            $this->normalizeBinaryFormat($this->maxSize);
        }
    }

    /**
     * @param string $option
     * @param mixed $value
     */
    public function __set($option, $value): void
    {
        if ('maxSize' === $option) {
            $this->normalizeBinaryFormat($value);

            return;
        }

        parent::__set($option, $value);
    }

    public function __get($option)
    {
        if ('maxSize' === $option) {
            return $this->maxSize;
        }

        return parent::__get($option);
    }

    /**
     * @param string $option
     * @return bool
     */
    public function __isset($option): bool
    {
        if ('maxSize' === $option) {
            return true;
        }

        return parent::__isset($option);
    }

    /**
     * @param mixed $maxSize
     */
    private function normalizeBinaryFormat($maxSize): void
    {
        $factors = [
            'k' => 1000,
            'ki' => 1 << 10,
            'm' => 1000 * 1000,
            'mi' => 1 << 20,
            'g' => 1000 * 1000 * 1000,
            'gi' => 1 << 30,
        ];
        if (ctype_digit((string) $maxSize)) {
            $this->maxSize = (int) $maxSize;
            $this->binaryFormat = null === $this->binaryFormat ? false : $this->binaryFormat;
        } elseif (preg_match('/^(\d++)('. implode('|', array_keys($factors)).')$/i', $maxSize, $matches)) {
            $this->maxSize = $matches[1] * $factors[$unit = strtolower($matches[2])];
            $this->binaryFormat = null === $this->binaryFormat ? 2 === \strlen($unit) : $this->binaryFormat;
        } else {
            throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $this->maxSize));
        }
    }

    public function validatedBy(): string
    {
        return UploadedWebFileValidator::class;
    }
}
