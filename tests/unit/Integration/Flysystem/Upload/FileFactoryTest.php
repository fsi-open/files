<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Integration\Flysystem\Upload;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use FSi\Component\Files\Integration\FlySystem\Upload\FileFactory;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Uri;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

use function codecept_data_dir;
use function fopen;

final class FileFactoryTest extends Unit
{
    public function testCreatingFromParameters(): void
    {
        $fileFactory = new FileFactory(
            $this->makeEmpty(ClientInterface::class, [
                'sendRequest' => Expected::never()
            ]),
            $this->makeEmpty(RequestFactoryInterface::class, [
                'createRequest' => Expected::never()
            ]),
            $this->makeEmpty(StreamFactoryInterface::class, [
                'createStream' => Expected::never(),
                'createStreamFromFile' => Expected::never(),
                'createStreamFromResource' => Expected::never()
            ])
        );

        /** @var resource $fileHandle */
        $fileHandle = fopen(codecept_data_dir('test.jpg'), 'r');
        $file = $fileFactory->create(new Stream($fileHandle), 'test.jpg', 'image/jpeg', 9111, 0);

        self::assertSame('test.jpg', $file->getOriginalName());
        self::assertSame('image/jpeg', $file->getMimeType());
        self::assertSame(9111, $file->getSize());
        self::assertSame(0, $file->getError());
    }

    public function testCreatingFromPath(): void
    {
        $fileFactory = new FileFactory(
            $this->makeEmpty(ClientInterface::class, [
                'sendRequest' => Expected::never()
            ]),
            $this->makeEmpty(RequestFactoryInterface::class, [
                'createRequest' => Expected::never()
            ]),
            $this->makeEmpty(StreamFactoryInterface::class, [
                'createStream' => Expected::never(),
                'createStreamFromFile' => Expected::exactly(
                    2,
                    static function (string $path): Stream {
                        /** @var resource $fileHandle */
                        $fileHandle = fopen($path, 'r');
                        return new Stream($fileHandle);
                    }
                ),
                'createStreamFromResource' => Expected::never()
            ])
        );

        $path = codecept_data_dir('test.jpg');
        $file1 = $fileFactory->createFromPath($path);

        self::assertSame('test.jpg', $file1->getOriginalName());
        self::assertSame('image/jpeg', $file1->getMimeType());
        self::assertSame(0, $file1->getError());

        $file2 = $fileFactory->createFromPath($path, 'overwritten_name.jpg');

        self::assertSame('overwritten_name.jpg', $file2->getOriginalName());
        self::assertSame('image/jpeg', $file2->getMimeType());
    }

    public function testCreatingFromUri(): void
    {
        $fileFactory = new FileFactory(
            $this->makeEmpty(ClientInterface::class, [
                'sendRequest' => Expected::exactly(
                    2,
                    function (RequestInterface $request): ResponseInterface {
                        /** @var resource $fileHandle */
                        $fileHandle = fopen(codecept_data_dir('test.jpg'), 'r');
                        $response = (new Response())
                            ->withStatus(200)
                            ->withBody(new Stream($fileHandle))
                        ;

                        if ('https://exmaple.com/image_1.jpg' === (string) $request->getUri()) {
                            $response = $response->withHeader('Content-Type', 'image/JPEG;charset=utf8');
                        }

                        return $response;
                    }
                )
            ]),
            $this->makeEmpty(RequestFactoryInterface::class, [
                'createRequest' => Expected::exactly(
                    2,
                    static fn(string $method, UriInterface $uri): RequestInterface
                        => new Request($method, $uri)
                )
            ]),
            $this->makeEmpty(StreamFactoryInterface::class, [
                'createStream' => Expected::never(),
                'createStreamFromFile' => Expected::never(),
                'createStreamFromResource' => Expected::never()
            ])
        );

        // Original image name
        $file = $fileFactory->createFromUri(
            new Uri('https://exmaple.com/image_1.jpg')
        );

        self::assertSame('image_1.jpg', $file->getOriginalName());
        self::assertSame('image/jpeg', $file->getMimeType());
        self::assertSame(9111, $file->getSize());
        self::assertSame(UPLOAD_ERR_OK, $file->getError());

        // Overwritten name
        $file = $fileFactory->createFromUri(
            new Uri('https://exmaple.com/image_2.jpg'),
            'test_image.jpeg'
        );

        self::assertSame('test_image.jpeg', $file->getOriginalName());
        self::assertSame('image/jpeg', $file->getMimeType());
        self::assertSame(9111, $file->getSize());
        self::assertSame(UPLOAD_ERR_OK, $file->getError());
    }

    public function testFileNotFoundException(): void
    {
        $fileFactory = new FileFactory(
            $this->makeEmpty(ClientInterface::class, [
                'sendRequest' => Expected::once(
                    function (RequestInterface $request): ResponseInterface {
                        self::assertSame('GET', $request->getMethod());
                        self::assertSame(
                            'https://exmaple.com/image_404.jpg',
                            (string) $request->getUri()
                        );

                        return (new Response())->withStatus(404);
                    }
                )
            ]),
            $this->makeEmpty(RequestFactoryInterface::class, [
                'createRequest' => Expected::once(new Request('GET', 'https://exmaple.com/image_404.jpg'))
            ]),
            $this->makeEmpty(StreamFactoryInterface::class, [
                'createStream' => Expected::never(),
                'createStreamFromFile' => Expected::never(),
                'createStreamFromResource' => Expected::never()
            ])
        );

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage(
            'Uri "https://exmaple.com/image_404.jpg" is not reachable (HTTP error "404").'
        );

        $fileFactory->createFromUri(new Uri('https://exmaple.com/image_404.jpg'));
    }

    public function testInternalServerErrorException(): void
    {
        $fileFactory = new FileFactory(
            $this->makeEmpty(ClientInterface::class, [
                'sendRequest' => Expected::once(
                    function (RequestInterface $request): ResponseInterface {
                        self::assertSame('GET', $request->getMethod());
                        self::assertSame(
                            'https://exmaple.com/image_500.jpg',
                            (string) $request->getUri()
                        );

                        return (new Response())->withStatus(500);
                    }
                )
            ]),
            $this->makeEmpty(RequestFactoryInterface::class, [
                'createRequest' => Expected::once(new Request('GET', 'https://exmaple.com/image_500.jpg'))
            ]),
            $this->makeEmpty(StreamFactoryInterface::class, [
                'createStream' => Expected::never(),
                'createStreamFromFile' => Expected::never(),
                'createStreamFromResource' => Expected::never()
            ])
        );

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage(
            'Uri "https://exmaple.com/image_500.jpg" is not reachable (HTTP error "500").'
        );

        $fileFactory->createFromUri(new Uri('https://exmaple.com/image_500.jpg'));
    }
}
