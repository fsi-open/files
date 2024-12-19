<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Module;

use Assert\Assertion;
use Aws\MockHandler;
use Codeception\Lib\Connector\Symfony as SymfonyConnector;
use Codeception\Module;
use Codeception\Module\Symfony;
use Codeception\TestInterface;
use FSi\Component\Files\DirectUpload\DirectUploadTargetEncryptor;
use FSi\Component\Files\Upload\FileFactory;
use FSi\Component\Files\Upload\PhpFilesHandler;
use FSi\Component\Files\UploadedWebFile;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;

use function codecept_data_dir;
use function json_decode;
use function mime_content_type;

use const JSON_THROW_ON_ERROR;

final class UploadModule extends Module
{
    private Symfony $symfony;
    private Module\REST $rest;

    /**
     * @phpcs:disable
     */
    public function _before(TestInterface $test): void
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        $this->symfony = $symfony;

        /** @var Module\REST $rest */
        $rest = $this->getModule('REST');
        $this->rest = $rest;
    }

    /**
     * @param string $sourceFileName
     * @param string $targetFileSystem
     * @param string $targetPrefix
     * @return array{
     *     url: string,
     *     fileSystem: string,
     *     key: string,
     *     publicUrl: string|null,
     *     headers: array<string, string>
     * }
     */
    public function prepareTemporaryUploadParameters(
        string $sourceFileName,
        string $targetFileSystem,
        string $targetPrefix = 'temporary'
    ): array {
        $contentType = mime_content_type(codecept_data_dir($sourceFileName));

        $response = $this->rest->sendPost('/upload/params', [
            'fileSystemName' => $targetFileSystem,
            'fileSystemPrefix' => $targetPrefix,
            'filename' => $sourceFileName,
            'contentType' => $contentType,
        ]);
        $this->rest->seeResponseCodeIs(200);

        return $this->decodeParamsResponse($response);
    }

    /**
     * @param string $sourceFileName
     * @param string $targetEntity
     * @param string $targetProperty
     * @return array{
     *     url: string,
     *     fileSystem: string,
     *     key: string,
     *     publicUrl: string|null,
     *     headers: array<string, string>
     * }
     */
    public function prepareEntityUploadParameters(
        string $sourceFileName,
        string $targetEntity,
        string $targetProperty
    ): array {
        $contentType = mime_content_type(codecept_data_dir($sourceFileName));

        $targetEncryptor = $this->symfony->grabService(DirectUploadTargetEncryptor::class);
        Assertion::isInstanceOf($targetEncryptor, DirectUploadTargetEncryptor::class);
        $target = $targetEncryptor->encrypt($targetEntity, $targetProperty);

        $response = $this->rest->sendPost('/upload/params', [
            'target' => $target,
            'filename' => $sourceFileName,
            'contentType' => $contentType,
        ]);

        return $this->decodeParamsResponse($response);
    }

    /**
     * @param string $filename
     * @param string $fileSystem
     * @param string $path
     * @param array<string, string> $headers
     */
    public function haveUploadedFileDirectly(string $filename, string $fileSystem, string $path, array $headers): void
    {
        $sourceFilePath = codecept_data_dir($filename);
        $contentType = mime_content_type($sourceFilePath);

        foreach ($headers as $headerName => $headerValue) {
            $this->rest->haveHttpHeader($headerName, $headerValue);
        }
        $this->rest->sendPost(
            "/upload/{$fileSystem}/{$path}",
            [],
            [
                'file' => [
                    'name' => $filename,
                    'type' => $contentType,
                    'error' => 0,
                    'size' => filesize($sourceFilePath),
                    'tmp_name' => $sourceFilePath,
                ]
            ]
        );
    }

    public function grabAwsMockHandler(): MockHandler
    {
        $mockHandler = $this->symfony->grabService(MockHandler::class);
        Assertion::isInstanceOf($mockHandler, MockHandler::class);

        return $mockHandler;
    }

    /**
     * @return array<\FSi\Component\Files\UploadedWebFile|array<\FSi\Component\Files\UploadedWebFile>>
     */
    public function grabUploadedPhpFiles(): array
    {
        /** @var PhpFilesHandler $phpFilesHandler */
        $phpFilesHandler = $this->symfony->grabService(PhpFilesHandler::class);
        return $phpFilesHandler->readFiles();
    }

    public function grabFileFactory(): FileFactory
    {
        /** @var FileFactory $fileFactory */
        $fileFactory = $this->symfony->grabService('test.' . FileFactory::class);
        return $fileFactory;
    }

    public function grabClient(): SymfonyConnector
    {
        Assertion::notNull($this->symfony->client);

        return $this->symfony->client;
    }

    public function grabCrawler(): Crawler
    {
        return $this->grabClient()->getCrawler();
    }

    /**
     * @param string $response
     * @return array{
     *     url: string,
     *     fileSystem: string,
     *     key: string,
     *     publicUrl: string|null,
     *     headers: array<string, string>
     * }
     */
    private function decodeParamsResponse(string $response): array
    {
        /** @var array{
         *     url: string,
         *     fileSystem: string,
         *     key: string,
         *     publicUrl: string|null,
         *     headers: array<string, string>
         * } $responseData */
        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        Assert::assertArrayHasKey('url', $responseData);
        Assert::assertArrayHasKey('fileSystem', $responseData);
        Assert::assertArrayHasKey('key', $responseData);
        Assert::assertArrayHasKey('publicUrl', $responseData);
        Assert::assertArrayHasKey('headers', $responseData);
        Assert::assertArrayHasKey('x-expires-at', $responseData['headers']);
        Assert::assertArrayHasKey('x-signature', $responseData['headers']);

        return $responseData;
    }
}
