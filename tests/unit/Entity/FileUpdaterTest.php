<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Entity;

use Codeception\Test\Unit;
use FSi\Component\Files\Entity\Event\WebFileRemoved;
use FSi\Component\Files\Entity\Event\WebFileUsed;
use FSi\Component\Files\Entity\FileLoader;
use FSi\Component\Files\Entity\FileRemover;
use FSi\Component\Files\Entity\FileUpdater;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\Integration\FlySystem;
use FSi\Component\Files\Upload\FilePathGenerator;
use FSi\Component\Files\WebFile;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;

use function basename;

final class FileUpdaterTest extends Unit
{
    private string $filePathRegex = '[a-f0-9]{3}/[a-f0-9]{3}/[a-f0-9]{3}/[a-f0-9]{23}';

    private FilePropertyConfiguration $configuration;
    /**
     * @var EventDispatcherInterface&MockObject
     */
    private EventDispatcherInterface $eventDispatcher;
    /**
     * @var FileManager&MockObject
     */
    private FileManager $fileManager;
    private FileUpdater $fileUpdater;
    private FileRemover $fileRemover;

    public function testNotModifyingFile(): void
    {
        $file = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($file);

        $this->fileManager->expects($this->once())
            ->method('copy')
            ->with($file, 'fs', $this->matchesRegularExpression("#prefix/$this->filePathRegex/some-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): WebFile {
                    $file = $this->createMock(WebFile::class);
                    $file->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->method('getPath')->willReturn($path);

                    return new FlySystem\WebFile($fileSystemName, $path);
                }
            );

        $this->fileManager->expects(self::once())->method('exists')->willReturn(true);

        $this->fileUpdater->updateFiles($entity);
        $this->fileUpdater->flush();

        $file = $entity->getFile();
        $this->fileManager->expects($this->never())->method('copy');

        $this->eventDispatcher->expects(self::never())->method('dispatch');
        $this->fileUpdater->updateFiles($entity);
        $this->fileUpdater->flush();

        $this->assertSame($file, $entity->getFile());
    }

    public function testNewFileOnTargetFileSystem(): void
    {
        $file = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($file);

        $this->fileManager->expects($this->once())
            ->method('copy')
            ->with($file, 'fs', $this->matchesRegularExpression("#prefix/$this->filePathRegex/some-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): WebFile {
                    $file = $this->createMock(WebFile::class);
                    $file->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->method('getPath')->willReturn($path);

                    return new FlySystem\WebFile($fileSystemName, $path);
                }
            );

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn (WebFileUsed $event) =>
                    $event->getConfiguration() === $this->configuration
                    && $event->getEntity() === $entity
                    && $event->getFile() !== $file
                    && $event->getFile()->getFileSystemName() === $file->getFileSystemName()
                    && $event->getFile()->getPath() !== $file->getPath()
            ));

        $this->fileUpdater->updateFiles($entity);
        $this->fileUpdater->flush();

        $this->assertNotSame($file, $entity->getFile());
        $this->assertEquals('fs', $file->getFileSystemName());
        $newFilePath = $entity->getFilePath();
        $this->assertIsString($newFilePath);
        $this->assertEquals(basename($file->getPath()), basename($newFilePath));
        $this->assertNotEquals($newFilePath, $file->getPath());
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
                    $file->expects($this->atLeast(1))->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->atLeast(1))->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn (WebFileUsed $event) =>
                    $event->getConfiguration() === $this->configuration
                    && $event->getEntity() === $entity
                    && $event->getFile() !== $file
                    && $event->getFile()->getFileSystemName() === 'fs'
                    && $event->getFile()->getPath() !== $file->getPath()
            ));

        $this->fileUpdater->updateFiles($entity);
        $this->fileUpdater->flush();

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
                    $file->expects($this->atLeast(1))->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->atLeast(1))->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn (WebFileUsed $event) =>
                    $event->getConfiguration() === $this->configuration
                    && $event->getEntity() === $entity
                    && $event->getFile() !== $file
                    && $event->getFile()->getFileSystemName() === $file->getFileSystemName()
                    && $event->getFile()->getPath() !== $file->getPath()
            ));

        $this->fileManager->expects($this->never())->method('remove');
        $this->fileUpdater->updateFiles($entity);
        $this->fileUpdater->flush();

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($file, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->filePathRegex/some-path.dat#i", $entity->getFilePath());

        $this->fileRemover->flush();
    }

    public function testCloningFileFromAnotherEntity(): void
    {
        $file = new FlySystem\WebFile('fs', 'other/some-path.dat');
        $oldEntity = new TestEntity($file);

        $this->fileManager->expects($this->once())
            ->method('copy')
            ->with($file, 'fs', $this->matchesRegularExpression("#prefix/$this->filePathRegex/some-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): MockObject {
                    $file = $this->createMock(WebFile::class);
                    $file->expects($this->atLeast(1))->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->atLeast(1))->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $newEntity = new TestEntity($file);

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn (WebFileUsed $event) =>
                    $event->getConfiguration() === $this->configuration
                    && $event->getEntity() === $newEntity
                    && $event->getFile() !== $file
                    && $event->getFile()->getFileSystemName() === $file->getFileSystemName()
                    && $event->getFile()->getPath() !== $file->getPath()
            ));

        $this->fileManager->expects($this->never())->method('remove');
        $this->fileUpdater->updateFiles($newEntity);
        $this->fileUpdater->flush();

        $this->assertInstanceOf(WebFile::class, $newEntity->getFile());
        $this->assertNotSame($file, $newEntity->getFile());
        $this->assertEquals('fs', $newEntity->getFile()->getFileSystemName());
        $this->assertNotNull($newEntity->getFilePath());
        $this->assertRegExp("#prefix/$this->filePathRegex/some-path.dat#i", $newEntity->getFilePath());

        $this->fileRemover->flush();
    }

    public function testReplacingExistingFile(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $newFile = new FlySystem\WebFile('fs', 'prefix/some-new-path.dat');
        $entity->setFile($newFile);

        $this->eventDispatcher->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(
                fn (WebFileUsed|WebFileRemoved $event) =>
                    (
                        true === $event instanceof WebFileRemoved
                        && $event->getConfiguration() === $this->configuration
                        && $event->getEntity() === $entity
                        && $event->getFile() !== $oldFile
                    ) || (
                        true === $event instanceof WebFileUsed
                        && $event->getConfiguration() === $this->configuration
                        && $event->getEntity() === $entity
                        && $event->getFile() !== $newFile
                    )
            ));

        $this->fileManager->expects(self::once())->method('exists')->willReturn(true);
        $this->fileManager->expects($this->once())->method('remove')->with($oldFile);
        $this->fileUpdater->updateFiles($entity);
        $this->fileUpdater->flush();

        $this->assertNotSame($newFile, $entity->getFile());

        $this->fileRemover->flush();
    }

    public function testReplacingExistingFileWithNewTemporaryFile(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $tempFile = new FlySystem\WebFile('temp', 'some-new-path.dat');
        $entity->setFile($tempFile);

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

        $this->eventDispatcher->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(
                fn (WebFileUsed|WebFileRemoved $event) =>
                    (
                        true === $event instanceof WebFileRemoved
                        && $event->getConfiguration() === $this->configuration
                        && $event->getEntity() === $entity
                        && $event->getFile()->getFileSystemName() === $oldFile->getFileSystemName()
                        && $event->getFile()->getPath() === $oldFile->getPath()
                    ) || (
                        true === $event instanceof WebFileUsed
                        && $event->getConfiguration() === $this->configuration
                        && $event->getEntity() === $entity
                        && $event->getFile() !== $tempFile
                    )
            ));

        $this->fileManager->expects(self::once())->method('exists')->willReturn(true);
        $this->fileManager->expects($this->once())->method('remove')->with($oldFile);
        $this->fileUpdater->updateFiles($entity);
        $this->fileUpdater->flush();

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

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn (WebFileRemoved $event) =>
                    $event->getConfiguration() === $this->configuration
                    && $event->getEntity() === $entity
                    && $event->getFile()->getFileSystemName() === $oldFile->getFileSystemName()
                    && $event->getFile()->getPath() === $oldFile->getPath()
            ));

        $this->fileManager->expects(self::once())->method('exists')->willReturn(true);
        $this->fileManager->expects($this->once())->method('remove')->with($oldFile);
        $this->fileUpdater->updateFiles($entity);

        $this->assertNull($entity->getFile());
        $this->assertNull($entity->getFilePath());

        $this->fileRemover->flush();
    }

    public function testMovingTemporaryWebFileToTargetLocation(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $tempFile = new FlySystem\TemporaryWebFile('temp', 'some-new-path.dat');
        $entity->setFile($tempFile);

        $this->fileManager->expects($this->once())
            ->method('move')
            ->with($tempFile, 'fs', $this->matchesRegularExpression("#prefix/$this->filePathRegex/some-new-path.dat#i"))
            ->willReturnCallback(
                function (WebFile $sourceFile, string $fileSystemName, string $path): WebFile {
                    $file = $this->createMock(WebFile::class);
                    $file->expects($this->atLeast(1))->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->expects($this->atLeast(1))->method('getPath')->willReturn($path);

                    return $file;
                }
            );

        $this->eventDispatcher->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(
                fn (WebFileUsed|WebFileRemoved $event) =>
                    (
                        true === $event instanceof WebFileRemoved
                        && $event->getConfiguration() === $this->configuration
                        && $event->getEntity() === $entity
                        && $event->getFile()->getFileSystemName() === $oldFile->getFileSystemName()
                        && $event->getFile()->getPath() === $oldFile->getPath()
                    ) || (
                        true === $event instanceof WebFileUsed
                        && $event->getConfiguration() === $this->configuration
                        && $event->getEntity() === $entity
                        && $event->getFile() !== $tempFile
                        && $event->getFile()->getFileSystemName() === $oldFile->getFileSystemName()
                        && $event->getFile()->getPath() !== $tempFile->getPath()
                    )
            ));

        $this->fileManager->expects(self::once())->method('exists')->willReturn(true);
        $this->fileManager->expects($this->once())->method('remove')->with($oldFile);
        $this->fileUpdater->updateFiles($entity);
        $this->fileUpdater->flush();

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($tempFile, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertRegExp("#prefix/$this->filePathRegex/some-new-path.dat#i", $entity->getFilePath());

        $this->fileRemover->flush();
    }

    public function testNotTouchingDirectlyUploadedWebFileToTargetLocation(): void
    {
        $oldFile = new FlySystem\WebFile('fs', 'prefix/some-path.dat');
        $entity = new TestEntity($oldFile);
        $entity->setFilePath('prefix/some-path.dat');

        $directUploadPath = FilePathGenerator::generate('some-new-path.dat', 'prefix');
        $directFile = new FlySystem\DirectlyUploadedWebFile('fs', $directUploadPath);
        $entity->setFile($directFile);

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn (WebFileUsed|WebFileRemoved $event) =>
                    (
                        true === $event instanceof WebFileRemoved
                        && $event->getConfiguration() === $this->configuration
                        && $event->getEntity() === $entity
                        && $event->getFile()->getFileSystemName() === $oldFile->getFileSystemName()
                        && $event->getFile()->getPath() === $oldFile->getPath()
                    ) || (
                        $event->getConfiguration() === $this->configuration
                        && $event->getEntity() === $entity
                        && $event->getFile() !== $directFile
                        && $event->getFile()->getFileSystemName() === $directFile->getFileSystemName()
                        && $event->getFile()->getPath() === $directFile->getPath()
                    )
            ));

        $this->fileManager->expects(self::exactly(2))->method('exists')->willReturn(true);
        $this->fileManager->expects(self::once())->method('remove')->with($oldFile);
        $this->fileUpdater->updateFiles($entity);

        $this->assertInstanceOf(WebFile::class, $entity->getFile());
        $this->assertNotSame($directFile, $entity->getFile());
        $this->assertEquals('fs', $entity->getFile()->getFileSystemName());
        $this->assertNotNull($entity->getFilePath());
        $this->assertSame($directUploadPath, $entity->getFilePath());

        $this->fileRemover->flush();
    }

    public function setUp(): void
    {
        $this->configuration = new FilePropertyConfiguration(TestEntity::class, 'file', 'fs', 'filePath', 'prefix');
        $configurationResolver = new FilePropertyConfigurationResolver([$this->configuration]);

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->fileManager = $this->createMock(FileManager::class);
        $this->fileManager->expects($this->any())
            ->method('load')
            ->willReturnCallback(
                function (string $fileSystemName, string $path): WebFile {
                    $file = $this->createMock(WebFile::class);
                    $file->method('getFileSystemName')->willReturn($fileSystemName);
                    $file->method('getPath')->willReturn($path);

                    return new FlySystem\WebFile($fileSystemName, $path);
                }
            );

        $fileLoader = new FileLoader($this->fileManager, $configurationResolver);
        $this->fileRemover = new FileRemover(
            $configurationResolver,
            $this->fileManager,
            $fileLoader,
            $this->eventDispatcher
        );

        $this->fileUpdater = new FileUpdater(
            $configurationResolver,
            $this->fileManager,
            $fileLoader,
            $this->fileRemover,
            $this->eventDispatcher
        );
    }
}
