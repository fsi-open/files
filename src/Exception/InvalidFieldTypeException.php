<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Exception;

use Exception;

use function sprintf;

final class InvalidFieldTypeException extends Exception
{
    /**
     * @param class-string $class
     * @param string $property
     * @param string $expectedType
     * @param string $actualType
     */
    public function __construct(string $class, string $property, string $expectedType, string $actualType)
    {
        parent::__construct(sprintf(
            'Field "%s" of class "%s" should be of type "%s", but it is "%s"',
            $property,
            $class,
            $expectedType,
            $actualType
        ));
    }
}
