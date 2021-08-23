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

final class FieldWithoutDefaultNullValueException extends Exception
{
    /**
     * @param class-string $class
     * @param string $property
     */
    public function __construct(string $class, string $property)
    {
        parent::__construct(
            "Field \"{$property}\" of class \"{$class}\" does not have a default null value"
        );
    }
}
