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

final class InvalidUnionFieldTypeException extends Exception
{
    /**
     * @param class-string $class
     * @param string $property
     * @param string $expectedType
     */
    public function __construct(string $class, string $property, string $expectedType)
    {
        parent::__construct(sprintf(
            'Union field "%s" of class "%s" does not contain the type "%s"',
            $property,
            $class,
            $expectedType
        ));
    }
}
