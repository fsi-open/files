<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Validator\Constraint;

use FSi\Component\Files;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use function count;
use function round;
use function strlen;
use function strstr;

/**
 * This is a copy of Symfony FileValidator, adjusted for the slight differences
 * between implementation of file upload.
 *
 * @see \Symfony\Component\Validator\Constraints\FileValidator
 */
class UploadedWebFileValidator extends ConstraintValidator
{
    private const KB_BYTES = 1000;
    private const MB_BYTES = 1000000;
    private const KIB_BYTES = 1024;
    private const MIB_BYTES = 1048576;

    /**
     * @var array<int, string>
     */
    private static $suffices = [
        1 => 'bytes',
        self::KB_BYTES => 'kB',
        self::MB_BYTES => 'MB',
        self::KIB_BYTES => 'KiB',
        self::MIB_BYTES => 'MiB',
    ];

    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (false === $constraint instanceof UploadedWebFile) {
            throw new RuntimeException(sprintf(
                'Expected "%s", got "%s"',
                UploadedWebFile::class,
                get_class($constraint)
            ));
        }

        if (true === $value instanceof Files\WebFile && false === $value instanceof Files\UploadedWebFile) {
            return;
        }

        if (false === $value instanceof Files\UploadedWebFile) {
            throw new UnexpectedValueException($value, Files\UploadedWebFile::class);
        }

        if (UPLOAD_ERR_OK !== $value->getError()) {
            switch ($value->getError()) {
                case UPLOAD_ERR_INI_SIZE:
                    $iniLimitSize = UploadedFile::getMaxFilesize();
                    if (null !== $constraint->maxSize && $constraint->maxSize < $iniLimitSize) {
                        $limitInBytes = $constraint->maxSize;
                        $binaryFormat = $constraint->binaryFormat;
                    } else {
                        $limitInBytes = $iniLimitSize;
                        $binaryFormat = null !== $constraint->binaryFormat ? $constraint->binaryFormat : true;
                    }

                    [, $limitAsString, $suffix] = $this->factorizeSizes(0, $limitInBytes, $binaryFormat);
                    $this->context->buildViolation($constraint->uploadIniSizeErrorMessage)
                        ->setParameter('{{ limit }}', $limitAsString)
                        ->setParameter('{{ suffix }}', $suffix)
                        ->setCode(strval(UPLOAD_ERR_INI_SIZE))
                        ->addViolation()
                    ;
                    return;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->context->buildViolation($constraint->uploadFormSizeErrorMessage)
                        ->setCode(strval(UPLOAD_ERR_FORM_SIZE))
                        ->addViolation()
                    ;
                    return;
                case UPLOAD_ERR_PARTIAL:
                    $this->context->buildViolation($constraint->uploadPartialErrorMessage)
                        ->setCode(strval(UPLOAD_ERR_PARTIAL))
                        ->addViolation()
                    ;
                    return;
                case UPLOAD_ERR_NO_FILE:
                    $this->context->buildViolation($constraint->uploadNoFileErrorMessage)
                        ->setCode(strval(UPLOAD_ERR_NO_FILE))
                        ->addViolation()
                    ;
                    return;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->context->buildViolation($constraint->uploadNoTmpDirErrorMessage)
                        ->setCode(strval(UPLOAD_ERR_NO_TMP_DIR))
                        ->addViolation()
                    ;
                    return;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->context->buildViolation($constraint->uploadCantWriteErrorMessage)
                        ->setCode(strval(UPLOAD_ERR_CANT_WRITE))
                        ->addViolation()
                    ;
                    return;
                case UPLOAD_ERR_EXTENSION:
                    $this->context->buildViolation($constraint->uploadExtensionErrorMessage)
                        ->setCode(strval(UPLOAD_ERR_EXTENSION))
                        ->addViolation()
                    ;
                    return;
                default:
                    $this->context->buildViolation($constraint->uploadErrorMessage)
                        ->setCode(null !== $value->getError() ? (string) $value->getError() : null)
                        ->addViolation()
                    ;
                    return;
            }
        }

        $sizeInBytes = $value->getSize();
        $basename = $value->getOriginalName();

        if (0 === $sizeInBytes) {
            $this->context->buildViolation($constraint->disallowEmptyMessage)
                ->setParameter('{{ name }}', $this->formatValue($basename))
                ->setCode(UploadedWebFile::EMPTY_ERROR)
                ->addViolation()
            ;
            return;
        }

        if (null !== $constraint->maxSize) {
            $limitInBytes = $constraint->maxSize;

            if ($sizeInBytes > $limitInBytes) {
                [$sizeAsString, $limitAsString, $suffix] = $this->factorizeSizes(
                    $sizeInBytes,
                    $limitInBytes,
                    $constraint->binaryFormat
                );

                $this->context->buildViolation($constraint->maxSizeMessage)
                    ->setParameter('{{ size }}', $sizeAsString)
                    ->setParameter('{{ limit }}', $limitAsString)
                    ->setParameter('{{ suffix }}', $suffix)
                    ->setParameter('{{ name }}', $this->formatValue($basename))
                    ->setCode(UploadedWebFile::TOO_LARGE_ERROR)
                    ->addViolation()
                ;
                return;
            }
        }

        $constraintMimeTypes = $constraint->mimeTypes;
        if (null === $constraintMimeTypes
            || '' === $constraintMimeTypes
            || (true === is_array($constraintMimeTypes) && 0 === count($constraintMimeTypes))
        ) {
            return;
        }

        $this->validateMimeTypes($constraintMimeTypes, $value->getMimeType(), $constraint->mimeTypesMessage, $basename);
    }

    /**
     * @param array<string>|string $mimeTypes
     * @param string $mime
     * @param string $message
     * @param string $basename
     */
    private function validateMimeTypes($mimeTypes, string $mime, string $message, string $basename): void
    {
        if (true === is_string($mimeTypes)) {
            $mimeTypes = (array) $mimeTypes;
        }

        if (0 === count($mimeTypes)) {
            return;
        }

        foreach ($mimeTypes as $mimeType) {
            if ($mimeType === $mime) {
                return;
            }

            $discrete = strstr($mimeType, '/*', true);
            if (false !== $discrete && strstr($mime, '/', true) === $discrete) {
                return;
            }
        }

        $this->context->buildViolation($message)
            ->setParameter('{{ type }}', $this->formatValue($mime))
            ->setParameter('{{ types }}', $this->formatValues($mimeTypes))
            ->setParameter('{{ name }}', $this->formatValue($basename))
            ->setCode(UploadedWebFile::INVALID_MIME_TYPE_ERROR)
            ->addViolation()
        ;
    }

    private static function moreDecimalsThan(string $double, int $numberOfDecimals): bool
    {
        return strlen($double) > strlen((string) round((float) $double, $numberOfDecimals));
    }

    /**
     * Convert the limit to the smallest possible number
     * (i.e. try "MB", then "kB", then "bytes").
     *
     * @param int $size
     * @param int $limit
     * @param bool|null $binaryFormat
     * @return array{string, string, string}
     */
    private function factorizeSizes(int $size, int $limit, ?bool $binaryFormat): array
    {
        if (true === $binaryFormat) {
            $coef = self::MIB_BYTES;
            $coefFactor = self::KIB_BYTES;
        } else {
            $coef = self::MB_BYTES;
            $coefFactor = self::KB_BYTES;
        }

        $limitAsString = (string) ($limit / $coef);

        // Restrict the limit to 2 decimals (without rounding! we
        // need the precise value)
        while (self::moreDecimalsThan($limitAsString, 2)) {
            $coef /= $coefFactor;
            $limitAsString = (string) ($limit / $coef);
        }

        // Convert size to the same measure, but round to 2 decimals
        $sizeAsString = (string) round($size / $coef, 2);

        // If the size and limit produce the same string output
        // (due to rounding), reduce the coefficient
        while ($sizeAsString === $limitAsString) {
            $coef /= $coefFactor;
            $limitAsString = (string) ($limit / $coef);
            $sizeAsString = (string) round($size / $coef, 2);
        }

        return [$sizeAsString, $limitAsString, self::$suffices[$coef]];
    }
}
