<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\EventListener;

use Codeception\Test\Unit;
use FSi\Component\Files\EventListener\EntityFileLoader;
use FSi\Component\Files\EventListener\EntityFileRemover;
use FSi\Component\Files\EventListener\EntityFileUpdater;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\Integration\FlySystem\WebFile;
use PHPUnit\Framework\MockObject\MockObject;
use function tmpfile;

final class EntityFileUpdaterTest extends Unit
{
    /**
     * @var string string
     */
    private $uuidRegex = '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}';

    /**
     * @var FileManager|MockObject
     */
    private $fileManager;

    /**
     * @var EntityFileRemover|MockObject
     */
    private $entityFileRemover;

    /**
     * @var EntityFileUpdater
     */
    private $entityFileUpdater;

    public function testNewFileOnTargetFileSystem(): void
    {
        $file = new WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($file);

        $this->fileManager->expects($this->never())->method('readStream');
        $this->fileManager->expects($this->never())->method('writeStream');

        $this->entityFileUpdater->updateFiles($entity);

        $this->assertSame($file, $entity->getFile());
        $this->assertEquals($file->getPath(), $entity->getFilePath());
    }

    public function testNewFileFromTempFileSystem(): void
    {
        $file = new WebFile('temp', 'some-path.dat');
        $entity = new TestEntity($file);

        $readStream = tmpfile();
        $this->fileManager
            ->expects($this->once())
            ->method('readStream')
            ->with($file)
            ->willReturn($readStream)
        ;
        $this->fileManager
            ->expects($this->once())
            ->method('writeStream')
            ->with(
                'fs',
                $this->matchesRegularExpression("#prefix/$this->uuidRegex/some-path.dat#i"),
                $readStream
            );

        $this->entityFileRemover->expects($this->once())->method('add')->with($file);
        $this->entityFileUpdater->updateFiles($entity);

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($file, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->uuidRegex/some-path.dat#i", $entity->getFilePath());
    }

    public function testNewFileFromDifferentPrefixOnTargetFilesystem(): void
    {
        $file = new WebFile('fs', 'other/some-path.dat');
        $entity = new TestEntity($file);

        $readStream = tmpfile();
        $this->fileManager
            ->expects($this->once())
            ->method('readStream')
            ->with($file)
            ->willReturn($readStream)
        ;
        $this->fileManager
            ->expects($this->once())
            ->method('writeStream')
            ->with(
                'fs',
                $this->matchesRegularExpression("#prefix/$this->uuidRegex/some-path.dat#i"),
                $readStream
            )
        ;

        $this->entityFileRemover->expects($this->never())->method('add');
        $this->entityFileUpdater->updateFiles($entity);

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($file, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->uuidRegex/some-path.dat#i", $entity->getFilePath());
    }

    public function testReplacingExistingFile(): void
    {
        $oldFile = new WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $newFile = new WebFile('fs', 'prefix/some-new-path.dat');
        $entity->setFile($newFile);

        $this->fileManager->expects($this->never())->method('readStream');
        $this->fileManager->expects($this->never())->method('writeStream');
        $this->entityFileRemover->expects($this->once())->method('add')->with($oldFile);

        $this->entityFileUpdater->updateFiles($entity);

        $this->assertSame($newFile, $entity->getFile());
    }

    public function testReplacingExistingFileWithNewTemporaryFile(): void
    {
        $oldFile = new WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $tempFile = new WebFile('temp', 'some-new-path.dat');
        $entity->setFile($tempFile);

        $readStream = tmpfile();
        $this->fileManager
            ->expects($this->once())
            ->method('readStream')
            ->with($tempFile)
            ->willReturn($readStream)
        ;
        $this->fileManager
            ->expects($this->once())
            ->method('writeStream')
            ->with(
                'fs',
                $this->matchesRegularExpression("#prefix/$this->uuidRegex/some-new-path.dat#i"),
                $readStream
            );

        $this->entityFileRemover
            ->expects($this->exactly(2))
            ->method('add')
            ->withConsecutive([$oldFile], [$tempFile])
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
        $oldFile = new WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $entity->setFile(null);

        $this->fileManager->expects($this->never())->method('readStream');
        $this->fileManager->expects($this->never())->method('writeStream');
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

        $this->fileManager = $this->createMock(FileManager::class);
        $this->entityFileRemover = $this->createMock(EntityFileRemover::class);

        $this->entityFileUpdater = new EntityFileUpdater(
            $configurationResolver,
            $this->fileManager,
            new EntityFileLoader($configurationResolver),
            $this->entityFileRemover,
            'temp'
        );
    }
}
