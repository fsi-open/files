<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Upload;

use Assert\Assertion;
use FSi\Component\Files\UploadedWebFile;
use Tests\FSi\FunctionalTester;

use function codecept_data_dir;
use function filesize;

use const UPLOAD_ERR_OK;

final class PhpFilesHandlerCest
{
    public function testAllFilesCorrectUpload(FunctionalTester $I): void
    {
        $testPdfPath = codecept_data_dir('test_pdf.pdf');
        $testJpgPath = codecept_data_dir('test.jpg');

        $_FILES = [
            'single_file' => [
                'name' => 'test_pdf.pdf',
                'type' => 'application/pdf',
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($testPdfPath),
                'tmp_name' => $testPdfPath
            ],
            'multiple_files' => [
                [
                    'name' => 'test.jpg',
                    'type' => 'image/jpeg',
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($testJpgPath),
                    'tmp_name' => $testJpgPath
                ]
            ]
        ];

        $uploadedFiles = $I->grabUploadedPhpFiles();
        $I->assertCount(2, $uploadedFiles);
        $I->assertInstanceOf(UploadedWebFile::class, $uploadedFiles['single_file']);
        $multipleFiles = $uploadedFiles['multiple_files'];
        Assertion::isArray($multipleFiles);
        $I->assertInstanceOf(UploadedWebFile::class, $multipleFiles[0]);
    }

    public function testCorruptedUpload(FunctionalTester $I): void
    {
        $testPdfPath = codecept_data_dir('test_pdf.pdf');

        $_FILES = [
            'single_file' => [
                'name' => '',
                'type' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
                'tmp_name' => null
            ],
            'multiple_files' => [
                [
                    'name' => 'test_pdf.pdf',
                    'type' => 'application/pdf',
                    'error' => UPLOAD_ERR_PARTIAL,
                    'size' => (int) (filesize($testPdfPath) / 2),
                    'tmp_name' => $testPdfPath
                ]
            ]
        ];

        $uploadedFiles = $I->grabUploadedPhpFiles();
        $I->assertCount(1, $uploadedFiles);

        $multipleFiles = $uploadedFiles['multiple_files'];
        Assertion::isArray($multipleFiles);
        $partialFile = $multipleFiles[0];
        $I->assertInstanceOf(UploadedWebFile::class, $partialFile);
        $I->assertEquals(UPLOAD_ERR_PARTIAL, $partialFile->getError());
    }
}
