<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\Entity;

use Codeception\Test\Unit;
use FSi\Component\Files\Entity\FileLoader;
use FSi\Component\Files\Entity\FileRemover;
use FSi\Component\Files\Entity\FileUpdater;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\Integration\FlySystem;
use FSi\Component\Files\WebFile;
use PHPUnit\Framework\MockObject\MockObject;

final class FileUpdaterTest extends Unit
{
    /**
     * @var string string
     */
    private $filePathRegex = '[a-f0-9]{3}/[a-f0-9]{3}/[a-f0-9]{3}/[a-f0-9]{23}';

    /**
     * @var FileManager|MockObject
     */
    private $fileManager;

    /**
     * @var FileUpdater
     */
    private $fileUpdater;

    /**
     * @var FileRemover
     */
    private $fileRemover;

    public function testNewFileOnTargetFileSystem(): void
    {
        $file = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($file);

        $this->fileUpdater->updateFiles($entity);

        $this->assertSame($file, $entity->getFile());
        $this->assertEquals($file->getPath(), $entity->getFilePath());
    }

    public function testNewFileFromTempFileSystem(): void
    {
        $file = new FlySystem\WebFile('temp', 'some-path.dat');
        $entity = new TestEntity($file);

        $this->fileManager->expects($this->once())
            ->method('copy')
            ->with($file, 'fs', $this->matchesRegularExpression("#prefix/$this->filePathRegex/some-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): MockObject {
                    $file = $this->createMock(WebFile::class);
                    $file->expects($this->once())->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->once())->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $this->fileUpdater->updateFiles($entity);

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($file, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->filePathRegex/some-path.dat#i", $entity->getFilePath());
    }

    public function testNewFileFromDifferentPrefixOnTargetFilesystem(): void
    {
        $file = new FlySystem\WebFile('fs', 'other/some-path.dat');
        $entity = new TestEntity($file);

        $this->fileManager->expects($this->once())
            ->method('copy')
            ->with($file, 'fs', $this->matchesRegularExpression("#prefix/$this->filePathRegex/some-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): MockObject {
                    $file = $this->createMock(WebFile::class);
                    $file->expects($this->once())->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->once())->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $this->fileManager->expects($this->never())->method('remove');
        $this->fileUpdater->updateFiles($entity);

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($file, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->filePathRegex/some-path.dat#i", $entity->getFilePath());

        $this->fileRemover->flush();
    }

    public function testReplacingExistingFile(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $newFile = new FlySystem\WebFile('fs', 'prefix/some-new-path.dat');
        $entity->setFile($newFile);

        $this->fileManager->expects($this->once())->method('load')->willReturn($oldFile);
        $this->fileManager->expects($this->once())->method('remove')->with($oldFile);
        $this->fileUpdater->updateFiles($entity);

        $this->assertSame($newFile, $entity->getFile());

        $this->fileRemover->flush();
    }

    public function testReplacingExistingFileWithNewTemporaryFile(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $tempFile = new FlySystem\WebFile('temp', 'some-new-path.dat');
        $entity->setFile($tempFile);

        $this->fileManager->expects($this->once())->method('load')->willReturn($oldFile);
        $this->fileManager->expects($this->once())
            ->method('copy')
            ->with($tempFile, 'fs', $this->matchesRegularExpression("#prefix/$this->filePathRegex/some-new-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): WebFile {
                    $file = $this->createMock(WebFile::class);
                    $file->expects($this->any())->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->once())->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $this->fileManager->expects($this->once())->method('remove')->with($oldFile);
        $this->fileUpdater->updateFiles($entity);

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($tempFile, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->filePathRegex/some-new-path.dat#i", $entity->getFilePath());

        $this->fileRemover->flush();
    }

    public function testRemovingExistingFile(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $entity->setFile(null);

        $this->fileManager->expects($this->once())->method('load')->willReturn($oldFile);
        $this->fileManager->expects($this->once())->method('remove')->with($oldFile);
        $this->fileUpdater->updateFiles($entity);

        $this->assertNull($entity->getFile());
        $this->assertNull($entity->getFilePath());

        $this->fileRemover->flush();
    }

    public function setUp(): void
    {
        $configurationResolver = new FilePropertyConfigurationResolver([
            new FilePropertyConfiguration(TestEntity::class, 'file', 'fs', 'filePath', 'prefix')
        ]);

        $this->fileManager = $this->createMock(FileManager::class);
        $fileLoader = new FileLoader($this->fileManager, $configurationResolver);
        $this->fileRemover = new FileRemover($configurationResolver, $this->fileManager, $fileLoader);

        $this->fileUpdater = new FileUpdater(
            $configurationResolver,
            $this->fileManager,
            $fileLoader,
            $this->fileRemover
        );
    }
}
