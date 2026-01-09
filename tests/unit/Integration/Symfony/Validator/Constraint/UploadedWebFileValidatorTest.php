<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Integration\Symfony\Validator\Constraint;

use Codeception\Test\Unit;
use FSi\Component\Files;
use FSi\Component\Files\Integration\FlySystem;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedWebFile;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedWebFileValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\FSi\Helper\ConstraintViolationAssertion;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function basename;
use function codecept_data_dir;
use function count;
use function fopen;

use const PHP_VERSION_ID;

/**
 * This is a copy of Symfony FileValidator test, adjusted for the slight differences
 * between implementation of file upload.
 *
 * @see \Symfony\Component\Validator\Tests\Constraints\FileValidatorTest
 */
final class UploadedWebFileValidatorTest extends Unit
{
    /**
     * @var StreamInterface
     */
    private $stream;

    /**
     * @var Files\FileManager&MockObject
     */
    private $fileManager;

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
        $this->validator->validate(null, new UploadedWebFile());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate('', new UploadedWebFile());

        $this->assertNoViolation();
    }

    public function testStandardWebFileIsNotValidated(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate(
            new FlySystem\WebFile('temp', codecept_data_dir('test_pdf.pdf')),
            new UploadedWebFile()
        );

        $this->assertNoViolation();
    }

    public function testExpectsUploadedWebFile(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->initialize($this->createContext());
        $this->validator->validate(new stdClass(), new UploadedWebFile());
    }

    public function testValidFile(): void
    {
        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), new UploadedWebFile());

        $this->assertNoViolation();
    }

    public function testMaxSizeExceeded(): void
    {
        $constraint = new UploadedWebFile([
            'maxSize' => 7944,
            'maxSizeMessage' => 'myMessage',
        ]);

        $this->fileManager->expects(self::exactly(2))->method('fileSize')->willReturn(7945);

        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), $constraint);
        $this->validator->validate($this->createTemporaryWebFile(), $constraint);
        $this->validator->validate($this->createDirectlyUploadedWebFile(), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', '7944')
            ->setParameter('{{ size }}', '7945')
            ->setParameter('{{ suffix }}', 'bytes')
            ->setParameter('{{ name }}', '"test_pdf.pdf"')
            ->setCode(File::TOO_LARGE_ERROR)
            ->buildNextViolation('myMessage')
            ->setParameter('{{ limit }}', '7944')
            ->setParameter('{{ size }}', '7945')
            ->setParameter('{{ suffix }}', 'bytes')
            ->setParameter('{{ name }}', '"temporary_pdf.pdf"')
            ->setCode(File::TOO_LARGE_ERROR)
            ->buildNextViolation('myMessage')
            ->setParameter('{{ limit }}', '7944')
            ->setParameter('{{ size }}', '7945')
            ->setParameter('{{ suffix }}', 'bytes')
            ->setParameter('{{ name }}', '"direct_pdf.pdf"')
            ->setCode(File::TOO_LARGE_ERROR)
            ->assertRaised()
        ;
    }

    public function testMaxSizeNotExceeded(): void
    {
        $this->fileManager->expects(self::exactly(2))->method('fileSize')->willReturn(7945);
        $constraint = new UploadedWebFile([
            'maxSize' => 7945,
            'maxSizeMessage' => 'myMessage'
        ]);

        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), $constraint);
        $this->validator->validate($this->createTemporaryWebFile(), $constraint);
        $this->validator->validate($this->createDirectlyUploadedWebFile(), $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidMaxSize(): void
    {
        $this->expectException(ConstraintDefinitionException::class);

        $this->validator->validate(
            $this->createUploadedFile(),
            new UploadedWebFile(['maxSize' => '1abc'])
        );
    }

    public function testBinaryFormat(): void
    {
        $constraint = new UploadedWebFile([
            'maxSize' => 1024,
            'binaryFormat' => true,
            'maxSizeMessage' => 'myMessage',
        ]);

        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', '1')
            ->setParameter('{{ size }}', '7.76')
            ->setParameter('{{ suffix }}', 'KiB')
            ->setParameter('{{ name }}', '"test_pdf.pdf"')
            ->setCode(File::TOO_LARGE_ERROR)
            ->assertRaised();
    }

    public function testValidMimeType(): void
    {
        $constraint = new UploadedWebFile(['mimeTypes' => ['application/pdf']]);
        $this->fileManager->expects(self::exactly(2))->method('fileSize')->willReturn(7945);
        $this->fileManager->expects(self::exactly(2))->method('mimeType')->willReturn('application/pdf');

        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), $constraint);
        $this->validator->validate($this->createTemporaryWebFile(), $constraint);
        $this->validator->validate($this->createDirectlyUploadedWebFile(), $constraint);

        $this->assertNoViolation();
    }

    public function testWildcardMimeType(): void
    {
        $constraint = new UploadedWebFile(['mimeTypes' => ['application/*']]);

        $this->validator->initialize($this->createContext());
        $this->validator->validate(
            $this->createUploadedFile(),
            $constraint
        );

        $this->assertNoViolation();
    }

    public function testInvalidMimeType(): void
    {
        $constraint = new UploadedWebFile([
            'mimeTypes' => ['image/png', 'image/jpg'],
            'mimeTypesMessage' => 'myMessage'
        ]);

        $this->fileManager->expects(self::exactly(2))->method('fileSize')->willReturn(7945);
        $this->fileManager->expects(self::exactly(2))->method('mimeType')->willReturn('application/pdf');

        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), $constraint);
        $this->validator->validate($this->createTemporaryWebFile(), $constraint);
        $this->validator->validate($this->createDirectlyUploadedWebFile(), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/png", "image/jpg"')
            ->setParameter('{{ name }}', '"test_pdf.pdf"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->buildNextViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/png", "image/jpg"')
            ->setParameter('{{ name }}', '"temporary_pdf.pdf"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->buildNextViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/png", "image/jpg"')
            ->setParameter('{{ name }}', '"direct_pdf.pdf"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->assertRaised()
        ;
    }

    public function testInvalidWildcardMimeType(): void
    {
        $constraint = new UploadedWebFile([
            'mimeTypes' => ['image/*', 'image/jpg'],
            'mimeTypesMessage' => 'myMessage'
        ]);

        $this->fileManager->expects(self::exactly(2))->method('fileSize')->willReturn(7945);
        $this->fileManager->expects(self::exactly(2))->method('mimeType')->willReturn('application/pdf');

        $this->validator->initialize($this->createContext());
        $this->validator->validate($this->createUploadedFile(), $constraint);
        $this->validator->validate($this->createTemporaryWebFile(), $constraint);
        $this->validator->validate($this->createDirectlyUploadedWebFile(), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/*", "image/jpg"')
            ->setParameter('{{ name }}', '"test_pdf.pdf"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->buildNextViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/*", "image/jpg"')
            ->setParameter('{{ name }}', '"temporary_pdf.pdf"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->buildNextViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/*", "image/jpg"')
            ->setParameter('{{ name }}', '"direct_pdf.pdf"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->assertRaised()
        ;
    }

    public function testDisallowEmpty(): void
    {
        $constraint = new UploadedWebFile([
            'disallowEmptyMessage' => 'myMessage',
        ]);

        $fileMock = $this->createMock(Files\UploadedWebFile::class);
        $fileMock->expects($this->once())->method('getSize')->willReturn(0);
        $fileMock->expects($this->once())->method('getOriginalName')->willReturn('test_pdf.pdf');
        $fileMock->expects($this->once())->method('getError')->willReturn(UPLOAD_ERR_OK);

        $this->fileManager->expects(self::exactly(2))->method('fileSize')->willReturn(0);
        $this->fileManager->expects(self::exactly(2))->method('filename')->willReturnCallback(
            static function (Files\WebFile $file): string {
                return basename($file->getPath());
            }
        );

        $this->validator->initialize($this->createContext());
        $this->validator->validate($fileMock, $constraint);
        $this->validator->validate($this->createTemporaryWebFile(), $constraint);
        $this->validator->validate($this->createDirectlyUploadedWebFile(), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ name }}', '"test_pdf.pdf"')
            ->setCode(File::EMPTY_ERROR)
            ->buildNextViolation('myMessage')
            ->setParameter('{{ name }}', '"temporary_pdf.pdf"')
            ->setCode(File::EMPTY_ERROR)
            ->buildNextViolation('myMessage')
            ->setParameter('{{ name }}', '"direct_pdf.pdf"')
            ->setCode(File::EMPTY_ERROR)
            ->assertRaised()
        ;
    }

    /**
     * @param int $error
     * @param string $message
     * @param array<string, mixed> $params
     * @param string|null $maxSize
     *
     * @dataProvider provideUploadedFileError
     */
    public function testUploadedFileError(
        int $error,
        string $message,
        array $params = [],
        ?string $maxSize = null
    ): void {
        $constraint = new UploadedWebFile([
            $message => 'myMessage',
            'maxSize' => $maxSize,
        ]);

        $fileMock = $this->createMock(Files\UploadedWebFile::class);
        $fileMock->expects($this->exactly(2))->method('getError')->willReturn($error);

        $this->validator->initialize($this->createContext());
        $this->validator->validate($fileMock, $constraint);

        $this->buildViolation('myMessage')
            ->setParameters($params)
            ->setCode((string) $error)
            ->assertRaised()
        ;
    }

    /**
     * @return array<array{int, string, array<string, mixed>, ?string}>
     */
    public function provideUploadedFileError(): array
    {
        // access FileValidator::factorizeSizes() private method to format max file size
        $reflection = new ReflectionClass(UploadedWebFileValidator::class);
        $method = $reflection->getMethod('factorizeSizes');
        if (PHP_VERSION_ID < 80500) {
            $method->setAccessible(true);
        }
        [, $limit, $suffix] = $method->invokeArgs(
            new UploadedWebFileValidator($this->createMock(Files\FileManager::class)),
            [0, UploadedFile::getMaxFilesize(), false]
        );

        return [
            [UPLOAD_ERR_FORM_SIZE, 'uploadFormSizeErrorMessage', [], null],
            [UPLOAD_ERR_PARTIAL, 'uploadPartialErrorMessage', [], null],
            [UPLOAD_ERR_NO_FILE, 'uploadNoFileErrorMessage', [], null],
            [UPLOAD_ERR_NO_TMP_DIR, 'uploadNoTmpDirErrorMessage', [], null],
            [UPLOAD_ERR_CANT_WRITE, 'uploadCantWriteErrorMessage', [], null],
            [UPLOAD_ERR_EXTENSION, 'uploadExtensionErrorMessage', [], null],
            // when no maxSize is specified on constraint, it should use the ini value
            [UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', [
                '{{ limit }}' => UploadedFile::getMaxFilesize() / 1048576,
                '{{ suffix }}' => 'MiB',
            ], null],
            // it should use the smaller limitation (maxSize option in this case)
            [UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', [
                '{{ limit }}' => 1,
                '{{ suffix }}' => 'bytes',
            ], '1'],
            // it correctly parses the maxSize option and not only uses simple string comparison
            // 1000M should be bigger than the ini value
            [UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', [
                '{{ limit }}' => $limit,
                '{{ suffix }}' => $suffix,
            ], '1000M'],
            // it correctly parses the maxSize option and not only uses simple string comparison
            // 1000M should be bigger than the ini value
            [UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', [
                '{{ limit }}' => '0.1',
                '{{ suffix }}' => 'MB',
            ], '100K']
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $fileHandler = fopen(codecept_data_dir('test_pdf.pdf'), 'r');
        if (false === $fileHandler) {
            throw new RuntimeException('Cannot read test_pdf.pdf');
        }

        $this->stream = new Stream($fileHandler);
        $this->fileManager = $this->createMock(Files\FileManager::class);
        $this->fileManager->method('filename')->willReturnCallback(
            static function (Files\WebFile $file): string {
                return basename($file->getPath());
            }
        );
        $this->validator = new UploadedWebFileValidator($this->fileManager);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->stream->close();
        $this->stream->detach();
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

    private function createUploadedFile(): FlySystem\UploadedWebFile
    {
        $size = $this->stream->getSize();
        if (null === $size || 0 === $size) {
            throw new RuntimeException('Test file "test_pdf.pdf" is empty');
        }

        return new FlySystem\UploadedWebFile(
            $this->stream,
            'test_pdf.pdf',
            'application/pdf',
            $size,
            UPLOAD_ERR_OK
        );
    }

    private function buildViolation(string $message): ConstraintViolationAssertion
    {
        return new ConstraintViolationAssertion($this->context, $message, $this->constraint);
    }

    private function assertNoViolation(): void
    {
        $violationsCount = count($this->context->getViolations());
        $this->assertSame(0, $violationsCount, sprintf('0 violation expected. Got %u.', $violationsCount));
    }

    private function createTemporaryWebFile(): FlySystem\TemporaryWebFile
    {
        $temporaryWebFile = new FlySystem\TemporaryWebFile('test', 'temporary_pdf.pdf');

        return $temporaryWebFile;
    }

    private function createDirectlyUploadedWebFile(): FlySystem\DirectlyUploadedWebFile
    {
        return new FlySystem\DirectlyUploadedWebFile('test', 'direct_pdf.pdf');
    }
}
