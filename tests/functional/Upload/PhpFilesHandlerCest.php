<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\Upload;

use FSi\Component\Files\UploadedWebFile;
use FSi\Tests\FunctionalTester;
use const UPLOAD_ERR_OK;
use function codecept_data_dir;
use function filesize;

final class PhpFilesHandlerCest
{
    public function testAllFilesCorrectUpload(FunctionalTester $I): void
    {
        $I->amOnPage('/native');
        $I->seeResponseCodeIs(200);

        $I->see('Single file', 'label');
        $I->see('Multiple files', 'label');

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

        $I->submitForm('form', [], 'Submit');

        $I->seeCurrentUrlEquals('/native');
        $I->seeResponseCodeIs(200);

        $uploadedFiles = $I->grabUploadedPhpFiles();
        $I->assertCount(2, $uploadedFiles);
        $I->assertInstanceOf(UploadedWebFile::class, $uploadedFiles['single_file']);
        $I->assertInstanceOf(UploadedWebFile::class, $uploadedFiles['multiple_files'][0]);
    }

    public function testCorruptedUpload(FunctionalTester $I): void
    {
        $I->amOnPage('/native');

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
                    'size' => intval(filesize($testPdfPath) / 2),
                    'tmp_name' => $testPdfPath
                ]
            ]
        ];

        $I->submitForm('form', [], 'Submit');

        $I->seeCurrentUrlEquals('/native');
        $I->seeResponseCodeIs(200);

        $uploadedFiles = $I->grabUploadedPhpFiles();
        $I->assertCount(1, $uploadedFiles);

        $partialFile = $uploadedFiles['multiple_files'][0];
        $I->assertInstanceOf(UploadedWebFile::class, $partialFile);
        $I->assertEquals(UPLOAD_ERR_PARTIAL, $partialFile->getError());
    }
}
