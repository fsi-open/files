<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Helper;

use PHPUnit\Framework\Assert;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function count;

/**
 * Copy of \Symfony\Component\Validator\Test\ConstraintViolationAssertion
 *
 * @internal
 */
final class ConstraintViolationAssertion
{
    /**
     * @var ExecutionContextInterface
     */
    private $context;

    /**
     * @var ConstraintViolationAssertion[]
     */
    private $assertions;

    /**
     * @var string
     */
    private $message;

    /**
     * @var array<string, mixed>
     */
    private $parameters = [];

    /**
     * @var string
     */
    private $invalidValue = 'InvalidValue';

    /**
     * @var string
     */
    private $propertyPath = 'property.path';

    /**
     * @var int|null
     */
    private $plural;

    /**
     * @var string
     */
    private $code;

    /**
     * @var Constraint|null
     */
    private $constraint;

    /**
     * @var string
     */
    private $cause;

    /**
     * @param ExecutionContextInterface $context
     * @param string $message
     * @param Constraint|null $constraint
     * @param array<ConstraintViolationAssertion> $assertions
     */
    public function __construct(
        ExecutionContextInterface $context,
        string $message,
        Constraint $constraint = null,
        array $assertions = []
    ) {
        $this->context = $context;
        $this->message = $message;
        $this->constraint = $constraint;
        $this->assertions = $assertions;
    }

    public function atPath(string $path): self
    {
        $this->propertyPath = $path;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setParameter(string $key, $value): self
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return $this
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function setInvalidValue(string $invalidValue): self
    {
        $this->invalidValue = $invalidValue;

        return $this;
    }

    public function setPlural(?int $number): self
    {
        $this->plural = $number;

        return $this;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function setCause(string $cause): self
    {
        $this->cause = $cause;

        return $this;
    }

    public function buildNextViolation(string $message): self
    {
        $assertions = $this->assertions;
        $assertions[] = $this;

        return new self($this->context, $message, $this->constraint, $assertions);
    }

    public function assertRaised(): void
    {
        $expected = [];
        foreach ($this->assertions as $assertion) {
            $expected[] = $assertion->getViolation();
        }
        $expected[] = $this->getViolation();

        $violations = iterator_to_array($this->context->getViolations());

        $expectedCount = count($expected);
        $violationsCount = count($violations);
        Assert::assertSame(
            $expectedCount,
            $violationsCount,
            sprintf('%u violation(s) expected. Got %u.', $expectedCount, $violationsCount)
        );

        reset($violations);
        foreach ($expected as $violation) {
            Assert::assertEquals($violation, current($violations));
            next($violations);
        }
    }

    private function getViolation(): ConstraintViolation
    {
        return new ConstraintViolation(
            '',
            $this->message,
            $this->parameters,
            $this->context->getRoot(),
            $this->propertyPath,
            $this->invalidValue,
            $this->plural,
            $this->code,
            $this->constraint,
            $this->cause
        );
    }
}
