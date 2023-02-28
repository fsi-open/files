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
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function get_class;
use function is_int;
use function mb_strlen;
use function sprintf;

final class BasenameLengthValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === $constraint instanceof BasenameLength) {
            throw new RuntimeException(sprintf(
                'Expected "%s", got "%s"',
                BasenameLength::class,
                get_class($constraint)
            ));
        }

        if (true === $value instanceof Files\WebFile && false === $value instanceof Files\UploadedWebFile) {
            return;
        }

        if (false === $value instanceof Files\UploadedWebFile) {
            throw new UnexpectedValueException($value, Files\UploadedWebFile::class);
        }

        $min = $constraint->min;
        $max = $constraint->max;
        if (null === $min && null === $max) {
            throw new RuntimeException('Neither min nor max parameters have been set');
        }

        if (null !== $min && false === is_int($min)) {
            throw new RuntimeException('Min parameter is not an integer');
        }

        if (null !== $max && false === is_int($max)) {
            throw new RuntimeException('Max parameter is not an integer');
        }

        if (null !== $min && 0 >= $min) {
            throw new RuntimeException("Min parameter of \"{$min}\" is not greater than 0");
        }

        if (null !== $max && 0 >= $max) {
            throw new RuntimeException("Max parameter of \"{$max}\"is not greater than 0");
        }

        $fileName = $value->getOriginalName();
        $fileNameLength = mb_strlen($fileName);
        if (0 === $fileNameLength) {
            // Unable to perform validation, probably an upload error
            return;
        }

        if (null !== $max && $max < $fileNameLength) {
            $exactlyOptionEnabled = $min == $max;

            $this->context->buildViolation($exactlyOptionEnabled ? $constraint->exactMessage : $constraint->maxMessage)
                ->setParameter('{{ limit }}', (string) $max)
                ->setInvalidValue($fileName)
                ->setPlural($max)
                ->addViolation()
            ;

            return;
        }

        if (null !== $min && $min > $fileNameLength) {
            $exactlyOptionEnabled = $min == $max;

            $this->context->buildViolation($exactlyOptionEnabled ? $constraint->exactMessage : $constraint->minMessage)
                ->setParameter('{{ limit }}', (string) $min)
                ->setInvalidValue($fileName)
                ->setPlural($min)
                ->addViolation()
            ;
        }
    }
}
