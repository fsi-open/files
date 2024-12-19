<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\DirectUpload\Controller;

use Tests\FSi\FunctionalTester;

use function codecept_data_dir;
use function json_decode;

final class DirectUploadControllerCest
{
    public function testTemporaryUploadParams(FunctionalTester $I): void
    {
        $fileSystem = 'public';
        $pathPrefix = 'temporary';
        $filename = 'test_pdf.pdf';
        $contentType = 'application/pdf';

        $response = $I->sendPost('/upload/params', [
            'fileSystemName' => $fileSystem,
            'fileSystemPrefix' => $pathPrefix,
            'filename' => $filename,
            'contentType' => $contentType,
        ]);
        $I->seeResponseCodeIs(200);

        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $I->assertArrayHasKey('url', $responseData);
        $I->assertArrayHasKey('key', $responseData);
        $I->assertArrayHasKey('headers', $responseData);
        $I->assertArrayHasKey('x-expires-at', $responseData['headers']);
        $I->assertArrayHasKey('x-signature', $responseData['headers']);

        $filePath = codecept_data_dir($filename);
        foreach ($responseData['headers'] as $headerName => $headerValue) {
            $I->haveHttpHeader((string) $headerName, (string) $headerValue);
        }
        $I->sendPost(
            "/upload/{$fileSystem}/{$responseData['key']}",
            [],
            ['file' => [
                'name' => $filename,
                'type' => $contentType,
                'error' => 0,
                'size' => filesize($filePath),
                'tmp_name' => $filePath,
            ]]
        );
        $I->seeResponseCodeIs(200);
    }
}
