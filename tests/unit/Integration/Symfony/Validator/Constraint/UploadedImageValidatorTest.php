<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\Integration\Symfony\Validator\Constraint;

use Codeception\Test\Unit;
use FSi\Component\Files\Integration\FlySystem\UploadedWebFile;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedImage;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedImageValidator;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedWebFileValidator;
use FSi\Tests\Helper\ConstraintViolationAssertion;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use function codecept_data_dir;
use function count;
use function fopen;

final class UploadedImageValidatorTest extends Unit
{
    /**
     * @var StreamInterface
     */
    private $stream;

    /**
     * @var resource
     */
    private $fileHandler;

    /**
     * @var UploadedWebFileValidator
     */
    private $validator;

    /**
     * @var ExecutionContextInterface
     */
    private $context;

    /**
     * @var Constraint
     */
    private $constraint;

    public function testNullIsValid(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate(null, new UploadedImage());
        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate('', new UploadedImage());
        $this->assertNoViolation();
    }

    public function testValidImage(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage());
        $this->assertNoViolation();
    }

    public function testValidSize(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'minWidth' => 610,
            'maxWidth' => 611,
            'minHeight' => 406,
            'maxHeight' => 407
        ]));

        $this->assertNoViolation();
    }

    public function testWidthTooSmall(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'minWidth' => 612,
            'minWidthMessage' => 'myMessage'
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '611')
            ->setParameter('{{ min_width }}', '612')
            ->setCode(UploadedImage::TOO_NARROW_ERROR)
            ->assertRaised()
        ;
    }

    public function testWidthTooBig(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'maxWidth' => 610,
            'maxWidthMessage' => 'myMessage'
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '611')
            ->setParameter('{{ max_width }}', '610')
            ->setCode(UploadedImage::TOO_WIDE_ERROR)
            ->assertRaised()
        ;
    }

    public function testHeightTooSmall(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'minHeight' => 408,
            'minHeightMessage' => 'myMessage'
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '407')
            ->setParameter('{{ min_height }}', '408')
            ->setCode(UploadedImage::TOO_LOW_ERROR)
            ->assertRaised()
        ;
    }

    public function testHeightTooBig(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'maxHeight' => 406,
            'maxHeightMessage' => 'myMessage'
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '407')
            ->setParameter('{{ max_height }}', '406')
            ->setCode(UploadedImage::TOO_HIGH_ERROR)
            ->assertRaised()
        ;
    }

    public function testPixelsTooFew(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'minPixels' => 248678,
            'minPixelsMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '248677')
            ->setParameter('{{ min_pixels }}', '248678')
            ->setParameter('{{ height }}', '407')
            ->setParameter('{{ width }}', '611')
            ->setCode(UploadedImage::TOO_FEW_PIXEL_ERROR)
            ->assertRaised();
    }

    public function testPixelsTooMany(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'maxPixels' => 248676,
            'maxPixelsMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '248677')
            ->setParameter('{{ max_pixels }}', '248676')
            ->setParameter('{{ height }}', '407')
            ->setParameter('{{ width }}', '611')
            ->setCode(UploadedImage::TOO_MANY_PIXEL_ERROR)
            ->assertRaised();
    }

    public function testInvalidMinWidth(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage(['minWidth' => '1abc']));
    }

    public function testInvalidMaxWidth(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage(['maxWidth' => '1abc']));
    }

    public function testInvalidMinHeight(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage(['minHeight' => '1abc']));
    }

    public function testInvalidMaxHeight(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage(['maxHeight' => '1abc']));
    }

    public function testInvalidMinPixels(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage(['minPixels' => '1abc']));
    }

    public function testInvalidMaxPixels(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage(['maxPixels' => '1abc']));
    }

    public function testRatioTooSmall(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'minRatio' => 2,
            'minRatioMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1.5)
            ->setParameter('{{ min_ratio }}', 2)
            ->setCode(UploadedImage::RATIO_TOO_SMALL_ERROR)
            ->assertRaised()
        ;
    }

    public function testRatioTooBig(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'maxRatio' => 1,
            'maxRatioMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1.5)
            ->setParameter('{{ max_ratio }}', 1)
            ->setCode(UploadedImage::RATIO_TOO_BIG_ERROR)
            ->assertRaised()
        ;
    }

    public function testMaxRatioUsesTwoDecimalsOnly(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'maxRatio' => 1.51,
            'maxRatioMessage' => 'myMessage',
        ]));

        $this->assertNoViolation();
    }

    public function testInvalidMinRatio(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage(['minRatio' => '1abc']));
    }

    public function testInvalidMaxRatio(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage(['maxRatio' => '1abc']));
    }

    public function testLandscapeNotAllowed(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedImage([
            'allowLandscape' => false,
            'allowLandscapeMessage' => 'myMessage',
        ]));
        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 611)
            ->setParameter('{{ height }}', 407)
            ->setCode(UploadedImage::LANDSCAPE_NOT_ALLOWED_ERROR)
            ->assertRaised()
        ;
    }

    public function testSquareNotAllowed(): void
    {
        $file = $this->createUploadedFileFromName('test_img_square.jpeg');

        $this->validator->initialize($this->createContext());
        $this->validator->validate($file, new UploadedImage([
            'allowSquare' => false,
            'allowSquareMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 100)
            ->setParameter('{{ height }}', 100)
            ->setCode(UploadedImage::SQUARE_NOT_ALLOWED_ERROR)
            ->assertRaised()
        ;
    }

    public function testPortraitNotAllowed(): void
    {
        $file = $this->createUploadedFileFromName('test_img_portrait.jpg');

        $this->validator->initialize($this->createContext());
        $this->validator->validate($file, new UploadedImage([
            'allowPortrait' => false,
            'allowPortraitMessage' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 55)
            ->setParameter('{{ height }}', 173)
            ->setCode(UploadedImage::PORTRAIT_NOT_ALLOWED_ERROR)
            ->assertRaised()
        ;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $fileHandler = fopen(codecept_data_dir('test.jpg'), 'r');
        if (false === $fileHandler) {
            throw new RuntimeException('Cannot read test.jpg');
        }

        $this->fileHandler = $fileHandler;
        $this->stream = new Stream($fileHandler);
        $this->validator = new UploadedImageValidator();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->stream->close();
        $this->stream->detach();
    }

    private function createUploadedFile(): UploadedWebFile
    {
        $size = $this->stream->getSize();
        if (null === $size || 0 === $size) {
            throw new RuntimeException('Test file "test.jpg" is empty');
        }

        return new UploadedWebFile(
            $this->stream,
            'test.jpg',
            'image/jpeg',
            $size,
            UPLOAD_ERR_OK
        );
    }

    private function createUploadedFileFromName(string $name): UploadedWebFile
    {
        $fileHandler = fopen(codecept_data_dir($name), 'r');
        if (false === $fileHandler) {
            throw new RuntimeException("Cannot read \"{$name}\"");
        }

        $stream = new Stream($fileHandler);
        $size = $stream->getSize();
        if (null === $size || 0 === $size) {
            throw new RuntimeException("Test file \"{$name}\" is empty");
        }

        return new UploadedWebFile(
            $stream,
            $name,
            'image/jpeg',
            $size,
            UPLOAD_ERR_OK
        );
    }

    private function buildViolation(string $message): ConstraintViolationAssertion
    {
        return new ConstraintViolationAssertion($this->context, $message, $this->constraint);
    }

    private function createContext(): ExecutionContextInterface
    {
        // Initialize the context with some constraint so that we can
        // successfully build a violation.
        $this->constraint = new NotNull();

        $context = new ExecutionContext(
            $this->createMock(ValidatorInterface::class),
            'root',
            $this->createMock(TranslatorInterface::class)
        );
        $context->setGroup('MyGroup');
        $context->setNode('InvalidValue', null, null, 'property.path');
        $context->setConstraint($this->constraint);

        $this->context = $context;
        return $context;
    }

    private function assertNoViolation(): void
    {
        $violationsCount = count($this->context->getViolations());
        $this->assertSame(0, $violationsCount, sprintf('0 violation expected. Got %u.', $violationsCount));
    }
}
