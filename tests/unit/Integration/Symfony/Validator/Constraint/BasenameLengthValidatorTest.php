<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Integration\Symfony\Validator\Constraint;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use FSi\Component\Files\Integration\FlySystem;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\BasenameLength;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\BasenameLengthValidator;
use FSi\Component\Files\UploadedWebFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\FSi\Helper\ConstraintViolationAssertion;

final class BasenameLengthValidatorTest extends Unit
{
    private BasenameLengthValidator $validator;
    private ExecutionContextInterface $context;
    private Constraint $constraint;

    public function testStandardWebFileIsNotValidated(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate(
            new FlySystem\WebFile('temp', codecept_data_dir('test_pdf.pdf')),
            new BasenameLength(['max' => 10])
        );

        $this->assertNoViolation();
    }

    public function testMinimalLengthMet(): void
    {
        $file = $this->makeEmpty(UploadedWebFile::class, [
            'getOriginalName' => Expected::exactly(2, '12345')
        ]);

        $this->validator->initialize($this->createContext());

        $this->validator->validate($file, new BasenameLength(['min' => 5, 'max' => null]));
        $this->assertNoViolation();

        $this->validator->validate($file, new BasenameLength(['min' => 4, 'max' => null]));
        $this->assertNoViolation();
    }

    public function testLessThanMinimalLength(): void
    {
        $file = $this->makeEmpty(UploadedWebFile::class, [
            'getOriginalName' => Expected::once('1234')
        ]);

        $this->validator->initialize($this->createContext());
        $this->validator->validate($file, new BasenameLength(['min' => 5, 'max' => null]));

        $this->buildViolation(
            'This value is too short. It should have {{ limit }} character or more.'
            . '|This value is too short. It should have {{ limit }} characters or more.'
        )
            ->setParameter('{{ limit }}', '5')
            ->setPlural(5)
            ->setInvalidValue('1234')
            ->assertRaised()
        ;
    }

    public function testMaximumLengthMet(): void
    {
        $file = $this->makeEmpty(UploadedWebFile::class, [
            'getOriginalName' => Expected::exactly(2, '12345')
        ]);

        $this->validator->initialize($this->createContext());

        $this->validator->validate($file, new BasenameLength(['min' => null, 'max' => 5]));
        $this->assertNoViolation();

        $this->validator->validate($file, new BasenameLength(['min' => null, 'max' => 6]));
        $this->assertNoViolation();
    }

    public function testMaximumLengthExceeded(): void
    {
        $file = $this->makeEmpty(UploadedWebFile::class, [
            'getOriginalName' => Expected::once('1234567')
        ]);

        $this->validator->initialize($this->createContext());
        $this->validator->validate($file, new BasenameLength(['min' => null, 'max' => 6]));

        $this->buildViolation(
            'This value is too long. It should have {{ limit }} character or less.'
            . '|This value is too long. It should have {{ limit }} characters or less.'
        )
            ->setParameter('{{ limit }}', '6')
            ->setPlural(6)
            ->setInvalidValue('1234567')
            ->assertRaised()
        ;
    }

    public function testExactLengthMet(): void
    {
        $file = $this->makeEmpty(UploadedWebFile::class, [
            'getOriginalName' => Expected::once('12345')
        ]);

        $this->validator->initialize($this->createContext());
        $this->validator->validate($file, new BasenameLength(['min' => 5, 'max' => 5]));

        $this->assertNoViolation();
    }

    public function testLessThanExactLength(): void
    {
        $file = $this->makeEmpty(UploadedWebFile::class, [
            'getOriginalName' => Expected::once('123')
        ]);

        $this->validator->initialize($this->createContext());
        $this->validator->validate($file, new BasenameLength(['min' => 4, 'max' => 4]));

        $this->buildViolation(
            'This value should have exactly {{ limit }} character.'
            . '|This value should have exactly {{ limit }} characters.'
        )
            ->setParameter('{{ limit }}', '4')
            ->setPlural(4)
            ->setInvalidValue('123')
            ->assertRaised()
        ;
    }
    public function testMoreThanExactLength(): void
    {
        $file = $this->makeEmpty(UploadedWebFile::class, [
            'getOriginalName' => Expected::once('12345')
        ]);

        $this->validator->initialize($this->createContext());
        $this->validator->validate($file, new BasenameLength(['min' => 4, 'max' => 4]));

        $this->buildViolation(
            'This value should have exactly {{ limit }} character.'
            . '|This value should have exactly {{ limit }} characters.'
        )
            ->setParameter('{{ limit }}', '4')
            ->setPlural(4)
            ->setInvalidValue('12345')
            ->assertRaised()
        ;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new BasenameLengthValidator();
    }

    private function buildViolation(string $message): ConstraintViolationAssertion
    {
        return new ConstraintViolationAssertion($this->context, $message, $this->constraint);
    }

    private function assertNoViolation(): void
    {
        $violationsCount = count($this->context->getViolations());
        self::assertSame(0, $violationsCount, sprintf('0 violation expected. Got %u.', $violationsCount));
    }

    private function createContext(): ExecutionContextInterface
    {
        // Initialize the context with some constraint so that we can
        // successfully build a violation.
        $this->constraint = new NotNull();

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('');

        $context = new ExecutionContext($this->createMock(ValidatorInterface::class), 'root', $translator);
        $context->setGroup('MyGroup');
        $context->setNode('InvalidValue', null, null, 'property.path');
        $context->setConstraint($this->constraint);

        $this->context = $context;
        return $context;
    }
}
