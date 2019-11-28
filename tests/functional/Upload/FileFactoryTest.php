<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\Upload;

use Codeception\Test\Unit;
use FSi\Component\Files\Integration\FlySystem\Upload\FileFactory;
use FSi\Component\Files\UploadedWebFile;
use GuzzleHttp\Psr7\Stream;
use function codecept_data_dir;
use function fopen;

final class FileFactoryTest extends Unit
{
    public function testCreatingFromParameters(): void
    {
        $path = codecept_data_dir('test.jpg');
        $this->assertFileExists($path);

        $fileHandle = fopen($path, 'r');
        $this->assertNotFalse($fileHandle, "File at path \"{$path}\" could not be read.");

        $stream = new Stream($fileHandle);

        /** @var UploadedWebFile $file */
        $file = (new FileFactory())->create($stream, 'test.jpg', 'image/jpeg', 9111, 0);

        $this->assertSame('test.jpg', $file->getOriginalName());
        $this->assertSame('image/jpeg', $file->getMimeType());
        $this->assertSame(9111, $file->getSize());
        $this->assertSame(0, $file->getError());
    }

    public function testCreatingFromPath(): void
    {
        $path = codecept_data_dir('test.jpg');
        $factory = new FileFactory();

        /** @var UploadedWebFile $file1 */
        $file1 = $factory->createFromPath($path);

        $this->assertSame('test.jpg', $file1->getOriginalName());
        $this->assertSame('image/jpeg', $file1->getMimeType());
        $this->assertSame(0, $file1->getError());

        /** @var UploadedWebFile $file2 */
        $file2 = $factory->createFromPath($path, 'overwritten_name.jpg');

        $this->assertSame('overwritten_name.jpg', $file2->getOriginalName());
        $this->assertSame('image/jpeg', $file2->getMimeType());
    }
}
