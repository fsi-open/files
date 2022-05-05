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

final class BasenameLength extends Constraint
{
    /**
     * @var mixed
     */
    public $min;
    /**
     * @var mixed
     */
    public $max;
    /**
     * @var string
     */
    public $maxMessage = 'This value is too long. It should have {{ limit }} character or less.'
        . '|This value is too long. It should have {{ limit }} characters or less.'
    ;
    /**
     * @var string
     */
    public $minMessage = 'This value is too short. It should have {{ limit }} character or more.'
        . '|This value is too short. It should have {{ limit }} characters or more.'
    ;
    /**
     * @var string
     */
    public $exactMessage = 'This value should have exactly {{ limit }} character.'
        . '|This value should have exactly {{ limit }} characters.'
    ;
}
