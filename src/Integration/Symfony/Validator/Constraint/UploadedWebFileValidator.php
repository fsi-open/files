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
use function is_array;
use function round;
use function strlen;
use function strstr;

use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

/**
 * This is a copy of Symfony FileValidator, adjusted for the slight differences
 * between implementation of file upload.
 *
 * Typed properties are not used on purpose, until Symfony decides to use them
 * as well;
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
    private static array $suffices = [
        1 => 'bytes',
        self::KB_BYTES => 'kB',
        self::MB_BYTES => 'MB',
        self::KIB_BYTES => 'KiB',
        self::MIB_BYTES => 'MiB',
    ];

    protected Files\FileManager $fileManager;

    public function __construct(Files\FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * @param mixed $value
     */
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

        if (
            true === $value instanceof Files\TemporaryWebFile
            || true === $value instanceof Files\DirectlyUploadedWebFile
        ) {
            $this->validateDirectlyUploadedWebFile($value, $constraint);
        } elseif (true === $value instanceof Files\UploadedWebFile) {
            $this->validateUploadedWebFile($value, $constraint);
        } elseif (true === $value instanceof Files\WebFile) {
            return;
        } else {
            throw new UnexpectedValueException($value, Files\UploadedWebFile::class);
        }
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

    private function validateUploadedWebFile(Files\UploadedWebFile $value, UploadedWebFile $constraint): void
    {
        if (UPLOAD_ERR_OK !== $value->getError()) {
            switch ($value->getError()) {
                case UPLOAD_ERR_INI_SIZE:
                    $iniLimitSize = (int) UploadedFile::getMaxFilesize();
                    if (null !== $constraint->maxSize && $constraint->maxSize < $iniLimitSize) {
                        $limitInBytes = $constraint->maxSize;
                        $binaryFormat = $constraint->binaryFormat;
                    } else {
                        $limitInBytes = $iniLimitSize;
                        $binaryFormat = $constraint->binaryFormat ?? true;
                    }

                    [, $limitAsString, $suffix] = $this->factorizeSizes(0, $limitInBytes, $binaryFormat);
                    $this->context->buildViolation($constraint->uploadIniSizeErrorMessage)
                        ->setParameter('{{ limit }}', $limitAsString)
                        ->setParameter('{{ suffix }}', $suffix)
                        ->setCode((string) UPLOAD_ERR_INI_SIZE)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->context->buildViolation($constraint->uploadFormSizeErrorMessage)
                        ->setCode((string) UPLOAD_ERR_FORM_SIZE)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_PARTIAL:
                    $this->context->buildViolation($constraint->uploadPartialErrorMessage)
                        ->setCode((string) UPLOAD_ERR_PARTIAL)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_NO_FILE:
                    $this->context->buildViolation($constraint->uploadNoFileErrorMessage)
                        ->setCode((string) UPLOAD_ERR_NO_FILE)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->context->buildViolation($constraint->uploadNoTmpDirErrorMessage)
                        ->setCode((string) UPLOAD_ERR_NO_TMP_DIR)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->context->buildViolation($constraint->uploadCantWriteErrorMessage)
                        ->setCode((string) UPLOAD_ERR_CANT_WRITE)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_EXTENSION:
                    $this->context->buildViolation($constraint->uploadExtensionErrorMessage)
                        ->setCode((string) UPLOAD_ERR_EXTENSION)
                        ->addViolation();

                    return;
                default:
                    $this->context->buildViolation($constraint->uploadErrorMessage)
                        ->setCode(null !== $value->getError() ? (string) $value->getError() : null)
                        ->addViolation();

                    return;
            }
        }

        $basename = $value->getOriginalName();
        $this->validateSize($value->getSize(), $basename, $constraint);
        $this->validateMimeTypes($constraint, $value->getMimeType(), $constraint->mimeTypesMessage, $basename);
    }

    private function validateDirectlyUploadedWebFile(Files\WebFile $value, UploadedWebFile $constraint): void
    {
        $basename = $this->fileManager->filename($value);

        $sizeInBytes = $this->fileManager->fileSize($value);
        $this->validateSize($sizeInBytes, $basename, $constraint);

        $mimeType = $this->fileManager->mimeType($value);
        $this->validateMimeTypes($constraint, $mimeType, $constraint->mimeTypesMessage, $basename);
    }

    private function validateSize(int $sizeInBytes, string $basename, UploadedWebFile $constraint): void
    {
        if (0 === $sizeInBytes) {
            $this->context->buildViolation($constraint->disallowEmptyMessage)
                ->setParameter('{{ name }}', $this->formatValue($basename))
                ->setCode(UploadedWebFile::EMPTY_ERROR)
                ->addViolation();

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
                    ->addViolation();
            }
        }
    }

    private function validateMimeTypes(
        UploadedWebFile $constraint,
        string $mime,
        string $message,
        string $basename
    ): void {
        $mimeTypes = $constraint->mimeTypes;
        if (
            null === $mimeTypes
            || '' === $mimeTypes
            || (true === is_array($mimeTypes) && 0 === count($mimeTypes))
        ) {
            return;
        }

        if (true === is_string($mimeTypes)) {
            $mimeTypes = (array) $mimeTypes;
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
}
