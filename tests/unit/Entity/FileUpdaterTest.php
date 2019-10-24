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
use FSi\Component\Files\FileFactory;
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
    private $uuidRegex = '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}';

    /**
     * @var FileFactory|MockObject
     */
    private $fileFactory;

    /**
     * @var FileRemover|MockObject
     */
    private $entityFileRemover;

    /**
     * @var FileUpdater
     */
    private $entityFileUpdater;

    /**
     * @var WebFile|MockObject
     */
    private $mockFile;

    public function testNewFileOnTargetFileSystem(): void
    {
        $file = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($file);

        $this->entityFileUpdater->updateFiles($entity);

        $this->assertSame($file, $entity->getFile());
        $this->assertEquals($file->getPath(), $entity->getFilePath());
    }

    public function testNewFileFromTempFileSystem(): void
    {
        $file = new FlySystem\WebFile('temp', 'some-path.dat');
        $entity = new TestEntity($file);

        $this->fileFactory->expects($this->once())
            ->method('copy')
            ->with($file, 'fs', $this->matchesRegularExpression("#prefix/$this->uuidRegex/some-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): MockObject {
                    $file = $this->createMock(WebFile::class);
                    $file->expects($this->once())->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->once())->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $this->entityFileUpdater->updateFiles($entity);

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($file, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->uuidRegex/some-path.dat#i", $entity->getFilePath());
    }

    public function testNewFileFromDifferentPrefixOnTargetFilesystem(): void
    {
        $file = new FlySystem\WebFile('fs', 'other/some-path.dat');
        $entity = new TestEntity($file);

        $this->fileFactory->expects($this->once())
            ->method('copy')
            ->with($file, 'fs', $this->matchesRegularExpression("#prefix/$this->uuidRegex/some-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): MockObject {
                    $file = $this->createMock(WebFile::class);
                    $file->expects($this->once())->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->once())->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $this->entityFileUpdater->updateFiles($entity);

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($file, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->uuidRegex/some-path.dat#i", $entity->getFilePath());
    }

    public function testReplacingExistingFile(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $newFile = new FlySystem\WebFile('fs', 'prefix/some-new-path.dat');
        $entity->setFile($newFile);

        $this->fileFactory->expects($this->once())->method('createFromPath')->willReturn($oldFile);
        $this->fileFactory->expects($this->never())->method('copy');
        $this->entityFileRemover->expects($this->once())->method('add')->with($oldFile);

        $this->entityFileUpdater->updateFiles($entity);

        $this->assertSame($newFile, $entity->getFile());
    }

    public function testReplacingExistingFileWithNewTemporaryFile(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $tempFile = new FlySystem\WebFile('temp', 'some-new-path.dat');
        $entity->setFile($tempFile);

        $this->fileFactory->expects($this->once())->method('createFromPath')->willReturn($oldFile);

        $this->fileFactory->expects($this->once())
            ->method('copy')
            ->with($tempFile, 'fs', $this->matchesRegularExpression("#prefix/$this->uuidRegex/some-new-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): WebFile {
                    $file = $this->createMock(WebFile::class);
                    $file->expects($this->any())->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->once())->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $this->entityFileRemover
            ->expects($this->once())
            ->method('add')
            ->with($oldFile)
        ;

        $this->entityFileUpdater->updateFiles($entity);

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($tempFile, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->uuidRegex/some-new-path.dat#i", $entity->getFilePath());
    }

    public function testRemovingExistingFile(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $entity->setFile(null);

        $this->fileFactory->expects($this->once())->method('createFromPath')->willReturn($oldFile);
        $this->entityFileRemover->expects($this->once())->method('add')->with($oldFile);

        $this->entityFileUpdater->updateFiles($entity);

        $this->assertNull($entity->getFile());
        $this->assertNull($entity->getFilePath());
    }

    public function setUp(): void
    {
        $configurationResolver = new FilePropertyConfigurationResolver([
            new FilePropertyConfiguration(TestEntity::class, 'file', 'fs', 'filePath', 'prefix')
        ]);

        $this->fileFactory = $this->createMock(FileFactory::class);
        $this->entityFileRemover = $this->createMock(FileRemover::class);

        $this->mockFile = $this->createMock(WebFile::class);

        $this->entityFileUpdater = new FileUpdater(
            $configurationResolver,
            $this->fileFactory,
            new FileLoader($this->fileFactory, $configurationResolver),
            $this->entityFileRemover
        );
    }
}
