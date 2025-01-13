<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\DirectUpload\Controller;

use Tests\FSi\App\Entity\FileEntity;
use Tests\FSi\FunctionalTester;

use function codecept_data_dir;
use function sleep;
use function sprintf;
use function str_replace;

final class LocalUploadControllerCest
{
    public function testValidatingTemporarySignature(FunctionalTester $I): void
    {
        $fileSystem = 'public';
        $pathPrefix = 'temporary';
        $filename = 'test_pdf.pdf';

        $responseData = $I->prepareTemporaryUploadParameters($filename, $fileSystem, $pathPrefix);

        $validSignature = $responseData['headers']['x-signature'];
        $responseData['headers']['x-signature'] = 'invalid-signature';
        $I->haveUploadedFileDirectly(
            $filename,
            $responseData['url'],
            $responseData['headers']
        );
        $I->seeResponseCodeIs(403);

        $responseData['headers']['x-signature'] = $validSignature;
        sleep(2);
        $I->haveUploadedFileDirectly(
            $filename,
            $responseData['url'],
            $responseData['headers']
        );
        $I->seeResponseCodeIs(403);
    }

    public function testTemporaryUpload(FunctionalTester $I): void
    {
        $filename = 'test_pdf.pdf';
        $fileSystem = 'public';
        $pathPrefix = 'temporary';

        $responseData = $I->prepareTemporaryUploadParameters($filename, $fileSystem, $pathPrefix);

        $I->haveUploadedFileDirectly(
            $filename,
            $responseData['url'],
            $responseData['headers']
        );
        $I->seeResponseCodeIs(200);

        $uploadedPath = sprintf(
            '%s/../../../_support/project/public/files/%s',
            __DIR__,
            str_replace('/upload/public', '', $responseData['url'])
        );
        $I->assertFileExists($uploadedPath);
        $sourceFilePath = codecept_data_dir($filename);
        $I->assertFileEquals($sourceFilePath, $uploadedPath);
    }

    public function testValidatingEntitySignature(FunctionalTester $I): void
    {
        $filename = 'test_pdf.pdf';
        $targetEntity = FileEntity::class;
        $targetProperty = 'anotherFile';

        $responseData = $I->prepareEntityUploadParameters($filename, $targetEntity, $targetProperty);
        $validSignature = $responseData['headers']['x-signature'];
        $responseData['headers']['x-signature'] = 'invalid-signature';

        $I->haveUploadedFileDirectly(
            $filename,
            $responseData['url'],
            $responseData['headers']
        );
        $I->seeResponseCodeIs(403);

        $responseData['headers']['x-signature'] = $validSignature;
        sleep(2);
        $I->haveUploadedFileDirectly(
            $filename,
            $responseData['url'],
            $responseData['headers']
        );
        $I->seeResponseCodeIs(403);
    }

    public function testEntityUpload(FunctionalTester $I): void
    {
        $filename = 'test_pdf.pdf';
        $targetEntity = FileEntity::class;
        $targetProperty = 'privateFile';

        $responseData = $I->prepareEntityUploadParameters($filename, $targetEntity, $targetProperty);

        $I->haveUploadedFileDirectly(
            $filename,
            $responseData['url'],
            $responseData['headers']
        );
        $I->seeResponseCodeIs(200);

        $uploadedPath = sprintf(
            '%s/../../../_support/project/var/private_files/%s',
            __DIR__,
            str_replace('/upload/private', '', $responseData['url'])
        );
        $I->assertFileExists($uploadedPath);
        $sourceFilePath = codecept_data_dir($filename);
        $I->assertFileEquals($sourceFilePath, $uploadedPath);
    }
}
