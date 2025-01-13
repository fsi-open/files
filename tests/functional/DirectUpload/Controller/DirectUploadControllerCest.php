<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\DirectUpload\Controller;

use Assert\Assertion;
use Aws\CommandInterface;
use Aws\Result;
use Aws\ResultInterface;
use FSi\Component\Files\DirectUpload\DirectUploadTargetEncryptor;
use Nyholm\Psr7\Uri;
use Ramsey\Uuid\Uuid;
use Tests\FSi\App\Entity\FileEntity;
use Tests\FSi\FunctionalTester;

use function base64_encode;
use function codecept_data_dir;
use function explode;
use function json_decode;
use function mime_content_type;
use function openssl_cipher_iv_length;
use function parse_str;
use function random_bytes;

use const JSON_THROW_ON_ERROR;

final class DirectUploadControllerCest
{
    public function testRequiringAllTemporaryParameters(FunctionalTester $I): void
    {
        $fileSystem = 'public';
        $pathPrefix = 'temporary';
        $filename = 'test_pdf.pdf';
        $contentType = mime_content_type(codecept_data_dir($filename));

        $I->sendPost(
            '/upload/params',
            [
                'fileSystemPrefix' => $pathPrefix,
                'filename' => $filename,
                'contentType' => $contentType,
            ]
        );
        $I->seeResponseCodeIs(500);

        $I->sendPost(
            '/upload/params',
            [
                'fileSystemName' => $fileSystem,
                'filename' => $filename,
                'contentType' => $contentType,
            ]
        );
        $I->seeResponseCodeIs(500);

        $I->sendPost(
            '/upload/params',
            [
                'fileSystemName' => $fileSystem,
                'fileSystemPrefix' => $pathPrefix,
                'contentType' => $contentType,
            ]
        );
        $I->seeResponseCodeIs(500);

        $I->sendPost(
            '/upload/params',
            [
                'fileSystemName' => $fileSystem,
                'fileSystemPrefix' => $pathPrefix,
                'filename' => $filename,
            ]
        );
        $I->seeResponseCodeIs(500);
    }

    public function testTemporaryParameters(FunctionalTester $I): void
    {
        $fileSystem = 'public';
        $pathPrefix = 'temporary';
        $filename = 'test_pdf.pdf';

        $responseData = $I->prepareTemporaryUploadParameters($filename, $fileSystem, $pathPrefix);
        $I->assertArrayHasKey('x-expires-at', $responseData['headers']);
        $I->assertArrayHasKey('x-signature', $responseData['headers']);
        $I->assertStringStartsWith($pathPrefix, $responseData['key']);
        $I->assertStringEndsWith($filename, $responseData['key']);
        $I->assertStringStartsWith('/upload/public/' . $pathPrefix, $responseData['url']);
        $I->assertStringEndsWith($filename, $responseData['url']);
        $I->assertStringEndsWith($responseData['key'], $responseData['url']);
        $I->assertStringStartsWith('/files/' . $pathPrefix, $responseData['publicUrl'] ?? '');
        $I->assertStringEndsWith($filename, $responseData['publicUrl'] ?? '');
        $I->assertStringEndsWith($responseData['key'], $responseData['publicUrl'] ?? '');

        $fileSystem = 'private';
        $responseData = $I->prepareTemporaryUploadParameters($filename, $fileSystem, $pathPrefix);
        $I->assertStringStartsWith($pathPrefix, $responseData['key']);
        $I->assertArrayHasKey('x-expires-at', $responseData['headers']);
        $I->assertArrayHasKey('x-signature', $responseData['headers']);
        $I->assertStringStartsWith('/upload/private/' . $pathPrefix, $responseData['url']);
        $I->assertStringEndsWith($filename, $responseData['url']);
        $I->assertStringEndsWith($responseData['key'], $responseData['url']);
        $I->assertStringEndsWith($filename, $responseData['key']);
        $I->assertNull($responseData['publicUrl']);
    }

    public function testValidatingEncryptedTargetParameter(FunctionalTester $I): void
    {
        $filename = 'test_pdf.pdf';
        $targetEntity = FileEntity::class;
        $targetProperty = 'anotherFile';
        $contentType = mime_content_type(codecept_data_dir($filename));

        $targetEncryptor = $I->grabService(DirectUploadTargetEncryptor::class);
        Assertion::isInstanceOf($targetEncryptor, DirectUploadTargetEncryptor::class);
        $target = $targetEncryptor->encrypt($targetEntity, $targetProperty);

        $I->sendPost('/upload/params', [
            'target' => 'invalid',
            'filename' => $filename,
            'contentType' => $contentType,
        ]);
        $I->seeResponseCodeIs(500);

        $parts = explode('.', $target);
        Assertion::count($parts, 2);
        /** @var int<1,max>|false $ivLength */
        $ivLength = openssl_cipher_iv_length(DirectUploadTargetEncryptor::CIPHER_ALGO);
        Assertion::integer($ivLength);
        $iv = random_bytes($ivLength);

        $I->sendPost('/upload/params', [
            'target' => $parts[0] . '.' . base64_encode($iv),
            'filename' => $filename,
            'contentType' => $contentType,
        ]);
        $I->seeResponseCodeIs(500);
    }

    public function testRequiringAllEntityParameters(FunctionalTester $I): void
    {
        $filename = 'test_pdf.pdf';
        $targetEntity = FileEntity::class;
        $targetProperty = 'anotherFile';
        $contentType = mime_content_type(codecept_data_dir($filename));

        $targetEncryptor = $I->grabService(DirectUploadTargetEncryptor::class);
        Assertion::isInstanceOf($targetEncryptor, DirectUploadTargetEncryptor::class);
        $target = $targetEncryptor->encrypt($targetEntity, $targetProperty);

        $I->sendPost('/upload/params', [
            'target' => $target,
            'contentType' => $contentType,
        ]);
        $I->seeResponseCodeIs(500);

        $I->sendPost('/upload/params', [
            'target' => $target,
            'filename' => $filename,
        ]);
        $I->seeResponseCodeIs(500);
    }

    public function testEntityParameters(FunctionalTester $I): void
    {
        $filename = 'test_pdf.pdf';
        $targetEntity = FileEntity::class;
        $targetProperty = 'anotherFile';
        $responseData = $I->prepareEntityUploadParameters($filename, $targetEntity, $targetProperty);

        $I->assertArrayHasKey('x-expires-at', $responseData['headers']);
        $I->assertArrayHasKey('x-signature', $responseData['headers']);
        $I->assertStringStartsWith('anotherFile', $responseData['key']);
        $I->assertStringEndsWith($filename, $responseData['key']);
        $I->assertStringStartsWith('/upload/other_public', $responseData['url']);
        $I->assertStringEndsWith($filename, $responseData['url']);
        $I->assertStringEndsWith($responseData['key'], $responseData['url']);
        $I->assertStringStartsWith('/other_files/anotherFile', $responseData['publicUrl'] ?? '');
        $I->assertStringEndsWith($filename, $responseData['publicUrl'] ?? '');
        $I->assertStringEndsWith($responseData['key'], $responseData['publicUrl'] ?? '');

        $targetProperty = 'privateFile';
        $responseData = $I->prepareEntityUploadParameters($filename, $targetEntity, $targetProperty);

        $I->assertArrayHasKey('x-expires-at', $responseData['headers']);
        $I->assertArrayHasKey('x-signature', $responseData['headers']);
        $I->assertStringStartsWith('/upload/private/private-file', $responseData['url']);
        $I->assertStringEndsWith($filename, $responseData['url']);
        $I->assertStringEndsWith($responseData['key'], $responseData['url']);
        $I->assertStringStartsWith('private-file', $responseData['key']);
        $I->assertStringEndsWith($filename, $responseData['key']);
        $I->assertNull($responseData['publicUrl']);
    }

    public function testS3MultipartCreate(FunctionalTester $I): void
    {
        $fileSystem = 'remote';
        $pathPrefix = 'temporary';
        $filename = 'test_pdf.pdf';
        $contentType = mime_content_type(codecept_data_dir($filename));

        $uploadId = Uuid::uuid4()->toString();
        $I->grabAwsMockHandler()->append(function (CommandInterface $cmd) use ($I, $uploadId): ResultInterface {
            $I->assertEquals('CreateMultipartUpload', $cmd->getName());
            $I->assertEquals('test', $cmd['Bucket']);
            $I->assertIsString($cmd['Key']);

            return new Result([
                'Bucket' => $cmd['Bucket'],
                'Key' => $cmd['Key'],
                'UploadId' => $uploadId,
            ]);
        });

        $response = $I->sendPost(
            '/upload/multipart_create',
            [
                'fileSystemName' => $fileSystem,
                'fileSystemPrefix' => $pathPrefix,
                'filename' => $filename,
                'contentType' => $contentType,
            ]
        );
        $I->seeResponseCodeIs(200);

        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $I->assertArrayHasKey('publicUrl', $responseData);
        $url = new Uri($responseData['publicUrl']);
        $I->assertStringStartsWith('/' . $pathPrefix, $url->getPath());
        $I->assertStringEndsWith($filename, $url->getPath());
        parse_str($url->getQuery(), $queryParams);
        $I->assertArrayHasKey('X-Amz-Date', $queryParams);
        $I->assertArrayHasKey('X-Amz-SignedHeaders', $queryParams);
        $I->assertArrayHasKey('X-Amz-Expires', $queryParams);
        $I->assertArrayHasKey('X-Amz-Signature', $queryParams);
        $I->assertArrayHasKey('key', $responseData);
        $I->assertStringStartsWith($pathPrefix, $responseData['key']);
        $I->assertStringEndsWith($filename, $responseData['key']);
        $I->assertStringEndsWith($responseData['key'], $url->getPath());
        $I->assertArrayHasKey('uploadId', $responseData);
    }

    public function testS3MultipartParts(FunctionalTester $I): void
    {
        $fileSystem = 'remote';
        $pathPrefix = 'temporary';
        $filename = 'test_pdf.pdf';
        $uploadId = Uuid::uuid4()->toString();

        $I->grabAwsMockHandler()->append(
            function (CommandInterface $cmd) use ($I, $uploadId, $pathPrefix, $filename): ResultInterface {
                $I->assertEquals('ListParts', $cmd->getName());
                $I->assertEquals('test', $cmd['Bucket']);
                $I->assertEquals($pathPrefix . '/' . $filename, $cmd['Key']);
                $I->assertEquals($uploadId, $cmd['UploadId']);

                return new Result([
                    'Parts' => [
                        ['PartNumber' => 1, 'ETag' => 'etag1'],
                        ['PartNumber' => 2, 'ETag' => 'etag2'],
                        ['PartNumber' => 3, 'ETag' => 'etag3'],
                    ],
                ]);
            }
        );

        $response = $I->sendPost(
            '/upload/multipart_parts',
            [
                'fileSystemName' => $fileSystem,
                'uploadId' => $uploadId,
                'key' => $pathPrefix . '/' . $filename,
            ]
        );
        $I->seeResponseCodeIs(200);

        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $I->assertIsArray($responseData);
        $I->assertCount(3, $responseData);
        $I->assertSame(['PartNumber' => 1, 'ETag' => 'etag1'], $responseData[0]);
        $I->assertSame(['PartNumber' => 2, 'ETag' => 'etag2'], $responseData[1]);
        $I->assertSame(['PartNumber' => 3, 'ETag' => 'etag3'], $responseData[2]);
    }

    public function testS3MultipartSign(FunctionalTester $I): void
    {
        $fileSystem = 'remote';
        $pathPrefix = 'temporary';
        $filename = 'test_pdf.pdf';

        $uploadId = Uuid::uuid4()->toString();
        $response = $I->sendPost(
            '/upload/multipart_sign',
            [
                'fileSystemName' => $fileSystem,
                'uploadId' => $uploadId,
                'key' => $pathPrefix . '/' . $filename,
                'partNumber' => 1,
            ]
        );
        $I->seeResponseCodeIs(200);

        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $I->assertIsArray($responseData);
        $I->assertArrayHasKey('url', $responseData);
        $url = new Uri($responseData['url']);
        $I->assertStringStartsWith('/' . $pathPrefix, $url->getPath());
        $I->assertStringEndsWith($filename, $url->getPath());
        parse_str($url->getQuery(), $queryParams);
        $I->assertArrayHasKey('uploadId', $queryParams);
        $I->assertEquals($uploadId, $queryParams['uploadId']);
        $I->assertArrayHasKey('partNumber', $queryParams);
        $I->assertEquals(1, $queryParams['partNumber']);
        $I->assertArrayHasKey('X-Amz-Date', $queryParams);
        $I->assertArrayHasKey('X-Amz-SignedHeaders', $queryParams);
        $I->assertArrayHasKey('X-Amz-Expires', $queryParams);
        $I->assertArrayHasKey('X-Amz-Signature', $queryParams);
    }

    public function testS3MultipartComplete(FunctionalTester $I): void
    {
        $fileSystem = 'remote';
        $pathPrefix = 'temporary';
        $filename = 'test_pdf.pdf';

        $uploadId = Uuid::uuid4()->toString();

        $I->grabAwsMockHandler()->append(
            function (CommandInterface $cmd) use ($I, $pathPrefix, $filename, $uploadId): ResultInterface {
                $I->assertEquals('CompleteMultipartUpload', $cmd->getName());
                $I->assertEquals('test', $cmd['Bucket']);
                $I->assertEquals($pathPrefix . '/' . $filename, $cmd['Key']);
                $I->assertEquals($uploadId, $cmd['UploadId']);

                return new Result();
            }
        );

        $I->sendPost(
            '/upload/multipart_complete',
            [
                'fileSystemName' => $fileSystem,
                'uploadId' => $uploadId,
                'key' => $pathPrefix . '/' . $filename,
                'parts' => [
                    ['PartNumber' => 1, 'ETag' => 'etag1'],
                    ['PartNumber' => 2, 'ETag' => 'etag2'],
                    ['PartNumber' => 3, 'ETag' => 'etag3'],
                ],
            ]
        );
        $I->seeResponseCodeIs(200);
    }

    public function testS3MultipartAbort(FunctionalTester $I): void
    {
        $fileSystem = 'remote';
        $pathPrefix = 'temporary';
        $filename = 'test_pdf.pdf';

        $uploadId = Uuid::uuid4()->toString();

        $I->grabAwsMockHandler()->append(
            function (CommandInterface $cmd) use ($I, $pathPrefix, $filename, $uploadId): ResultInterface {
                $I->assertEquals('AbortMultipartUpload', $cmd->getName());
                $I->assertEquals('test', $cmd['Bucket']);
                $I->assertEquals($pathPrefix . '/' . $filename, $cmd['Key']);
                $I->assertEquals($uploadId, $cmd['UploadId']);

                return new Result();
            }
        );

        $I->sendPost(
            '/upload/multipart_abort',
            [
                'fileSystemName' => $fileSystem,
                'uploadId' => $uploadId,
                'key' => $pathPrefix . '/' . $filename,
            ]
        );
        $I->seeResponseCodeIs(200);
    }
}
