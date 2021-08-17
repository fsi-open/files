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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

use function count;
use function ctype_digit;
use function get_class;
use function getimagesizefromstring;
use function is_array;
use function is_numeric;
use function round;
use function sprintf;

/**
 * This is a copy of Symfony ImageValidator, adjusted for the slight differences
 * between implementation of file upload.
 *
 * Typed properties are not used on purpose, until Symfony decides to use them
 * as well;
 *
 * @see \Symfony\Component\Validator\Constraints\ImageValidator
 */
final class UploadedImageValidator extends UploadedWebFileValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (false === $constraint instanceof UploadedImage) {
            throw new RuntimeException(sprintf(
                'Expected "%s", got "%s"',
                UploadedImage::class,
                get_class($constraint)
            ));
        }

        if (true === $value instanceof Files\WebFile && false === $value instanceof Files\UploadedWebFile) {
            return;
        }

        $violations = count($this->context->getViolations());
        parent::validate($value, $constraint);

        $failed = count($this->context->getViolations()) !== $violations;
        if (true === $failed || null === $value) {
            return;
        }

        if (
            (null === $constraint->minWidth && null === $constraint->maxWidth)
            && (null === $constraint->minHeight && null === $constraint->maxHeight)
            && (null === $constraint->minPixels && null === $constraint->maxPixels)
            && (null === $constraint->minRatio && null === $constraint->maxRatio)
            && true === $constraint->allowSquare
            && true === $constraint->allowLandscape
            && true === $constraint->allowPortrait
            && false === $constraint->detectCorrupted
        ) {
            return;
        }

        $size = @getimagesizefromstring($value->getStream()->getContents());
        if (false === is_array($size) || 0 === count($size) || (0 === $size[0]) || (0 === $size[1])) {
            $this->context->buildViolation($constraint->sizeNotDetectedMessage)
                ->setCode(UploadedImage::SIZE_NOT_DETECTED_ERROR)
                ->addViolation()
            ;

            return;
        }

        $width = $size[0];
        $height = $size[1];

        if (null !== $constraint->minWidth && 0 !== $constraint->minWidth) {
            if (false === ctype_digit("$constraint->minWidth")) {
                throw new ConstraintDefinitionException(
                    sprintf('"%s" is not a valid minimum width.', $constraint->minWidth)
                );
            }

            if ($width < $constraint->minWidth) {
                $this->context->buildViolation($constraint->minWidthMessage)
                    ->setParameter('{{ width }}', "$width")
                    ->setParameter('{{ min_width }}', "$constraint->minWidth")
                    ->setCode(UploadedImage::TOO_NARROW_ERROR)
                    ->addViolation()
                ;

                return;
            }
        }

        if (null !== $constraint->maxWidth && 0 !== $constraint->maxWidth) {
            if (false === ctype_digit("$constraint->maxWidth")) {
                throw new ConstraintDefinitionException(
                    sprintf('"%s" is not a valid maximum width.', $constraint->maxWidth)
                );
            }

            if ($width > $constraint->maxWidth) {
                $this->context->buildViolation($constraint->maxWidthMessage)
                    ->setParameter('{{ width }}', "$width")
                    ->setParameter('{{ max_width }}', "$constraint->maxWidth")
                    ->setCode(UploadedImage::TOO_WIDE_ERROR)
                    ->addViolation()
                ;

                return;
            }
        }

        if (null !== $constraint->minHeight && 0 !== $constraint->minHeight) {
            if (false === ctype_digit("$constraint->minHeight")) {
                throw new ConstraintDefinitionException(
                    sprintf('"%s" is not a valid minimum height', $constraint->minHeight)
                );
            }

            if ($height < $constraint->minHeight) {
                $this->context->buildViolation($constraint->minHeightMessage)
                    ->setParameter('{{ height }}', "$height")
                    ->setParameter('{{ min_height }}', "$constraint->minHeight")
                    ->setCode(UploadedImage::TOO_LOW_ERROR)
                    ->addViolation()
                ;

                return;
            }
        }

        if (null !== $constraint->maxHeight && 0 !== $constraint->maxHeight) {
            if (false === ctype_digit("$constraint->maxHeight")) {
                throw new ConstraintDefinitionException(
                    sprintf('"%s" is not a valid maximum height', $constraint->maxHeight)
                );
            }

            if ($height > $constraint->maxHeight) {
                $this->context->buildViolation($constraint->maxHeightMessage)
                    ->setParameter('{{ height }}', "$height")
                    ->setParameter('{{ max_height }}', "$constraint->maxHeight")
                    ->setCode(UploadedImage::TOO_HIGH_ERROR)
                    ->addViolation()
                ;
            }
        }

        $pixels = $width * $height;
        if (null !== $constraint->minPixels && 0 !== $constraint->minPixels) {
            if (false === ctype_digit("$constraint->minPixels")) {
                throw new ConstraintDefinitionException(
                    sprintf('"%s" is not a valid minimum amount of pixels', $constraint->minPixels)
                );
            }

            if ($pixels < $constraint->minPixels) {
                $this->context->buildViolation($constraint->minPixelsMessage)
                    ->setParameter('{{ pixels }}', "$pixels")
                    ->setParameter('{{ min_pixels }}', "$constraint->minPixels")
                    ->setParameter('{{ height }}', "$height")
                    ->setParameter('{{ width }}', "$width")
                    ->setCode(UploadedImage::TOO_FEW_PIXEL_ERROR)
                    ->addViolation()
                ;
            }
        }

        if (null !== $constraint->maxPixels && 0 !== $constraint->maxPixels) {
            if (false === ctype_digit("$constraint->maxPixels")) {
                throw new ConstraintDefinitionException(
                    sprintf('"%s" is not a valid maximum amount of pixels', $constraint->maxPixels)
                );
            }

            if ($pixels > $constraint->maxPixels) {
                $this->context->buildViolation($constraint->maxPixelsMessage)
                    ->setParameter('{{ pixels }}', "$pixels")
                    ->setParameter('{{ max_pixels }}', "$constraint->maxPixels")
                    ->setParameter('{{ height }}', "$height")
                    ->setParameter('{{ width }}', "$width")
                    ->setCode(UploadedImage::TOO_MANY_PIXEL_ERROR)
                    ->addViolation()
                ;
            }
        }

        $ratio = round($width / $height, 2);
        if (null !== $constraint->minRatio && 0 != $constraint->minRatio) {
            if (false === is_numeric("$constraint->minRatio")) {
                throw new ConstraintDefinitionException(
                    sprintf('"%s" is not a valid minimum ratio', $constraint->minRatio)
                );
            }

            if ($ratio < $constraint->minRatio) {
                $this->context->buildViolation($constraint->minRatioMessage)
                    ->setParameter('{{ ratio }}', "$ratio")
                    ->setParameter('{{ min_ratio }}', "$constraint->minRatio")
                    ->setCode(UploadedImage::RATIO_TOO_SMALL_ERROR)
                    ->addViolation()
                ;
            }
        }

        if (null !== $constraint->maxRatio && 0 != $constraint->maxRatio) {
            if (false === is_numeric("$constraint->maxRatio")) {
                throw new ConstraintDefinitionException(
                    sprintf('"%s" is not a valid maximum ratio', $constraint->maxRatio)
                );
            }

            if ($ratio > $constraint->maxRatio) {
                $this->context->buildViolation($constraint->maxRatioMessage)
                    ->setParameter('{{ ratio }}', "$ratio")
                    ->setParameter('{{ max_ratio }}', "$constraint->maxRatio")
                    ->setCode(UploadedImage::RATIO_TOO_BIG_ERROR)
                    ->addViolation()
                ;
            }
        }

        if (false === $constraint->allowSquare && $width == $height) {
            $this->context->buildViolation($constraint->allowSquareMessage)
                ->setParameter('{{ width }}', "$width")
                ->setParameter('{{ height }}', "$height")
                ->setCode(UploadedImage::SQUARE_NOT_ALLOWED_ERROR)
                ->addViolation()
            ;
        }

        if (false === $constraint->allowLandscape && $width > $height) {
            $this->context->buildViolation($constraint->allowLandscapeMessage)
                ->setParameter('{{ width }}', "$width")
                ->setParameter('{{ height }}', "$height")
                ->setCode(UploadedImage::LANDSCAPE_NOT_ALLOWED_ERROR)
                ->addViolation()
            ;
        }

        if (false === $constraint->allowPortrait && $width < $height) {
            $this->context->buildViolation($constraint->allowPortraitMessage)
                ->setParameter('{{ width }}', "$width")
                ->setParameter('{{ height }}', "$height")
                ->setCode(UploadedImage::PORTRAIT_NOT_ALLOWED_ERROR)
                ->addViolation()
            ;
        }
    }
}
