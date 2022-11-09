<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Entity;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use FSi\Component\Files\Entity\FileLoader;
use FSi\Component\Files\Exception\FileNotFoundException;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\WebFile;

final class FileLoaderTest extends Unit
{
    public function testExceptionWhenFileExistenceChecksAreEnabled(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(
            'File for filesystem "fs" not found at path "filePath".'
        );

        $file = $this->makeEmpty(WebFile::class, [
            'getFileSystemName' => 'fs',
            'getPath' => 'filePath'
        ]);

        $fileManager = $this->makeEmpty(FileManager::class, [
            'load' => Expected::once(
                function (string $fileSystem, string $path) use ($file): WebFile {
                    self::assertSame('fs', $fileSystem);
                    self::assertSame('filePath', $path);

                    return $file;
                }
            ),
            'exists' => Expected::once(
                static function (WebFile $actualFile) use ($file): bool {
                    self::assertEquals($file, $actualFile);
                    return false;
                }
            )
        ]);

        $configuration = new FilePropertyConfiguration(
            TestEntity::class,
            'file',
            'fs',
            'filePath',
            'prefix',
            false
        );

        $testEntity = new TestEntity(null);
        $testEntity->setFilePath('filePath');

        $configurationResolver = new FilePropertyConfigurationResolver([$configuration]);
        $fileLoader = new FileLoader($fileManager, $configurationResolver);
        $fileLoader->loadEntityFiles($testEntity);
    }

    public function testNoExceptionWhenFileExistenceChecksAreDisabled(): void
    {
        $file = $this->makeEmpty(WebFile::class, [
            'getFileSystemName' => 'fs',
            'getPath' => 'filePath'
        ]);

        $fileManager = $this->makeEmpty(FileManager::class, [
            'load' => Expected::once(
                function (string $fileSystem, string $path) use ($file): WebFile {
                    self::assertSame('fs', $fileSystem);
                    self::assertSame('filePath', $path);

                    return $file;
                }
            ),
            'exists' => Expected::once(
                static function (WebFile $actualFile) use ($file): bool {
                    self::assertEquals($file, $actualFile);
                    return true;
                }
            )
        ]);

        $configuration = new FilePropertyConfiguration(
            TestEntity::class,
            'file',
            'fs',
            'filePath',
            'prefix',
            true
        );

        $testEntity = new TestEntity(null);
        $testEntity->setFilePath('filePath');

        $configurationResolver = new FilePropertyConfigurationResolver([$configuration]);
        $fileLoader = new FileLoader($fileManager, $configurationResolver);
        $fileLoader->loadEntityFiles($testEntity);
    }

    public function testSwitchingFileExistenceFlag(): void
    {
        $file = $this->makeEmpty(WebFile::class, [
            'getFileSystemName' => 'fs',
            'getPath' => 'filePath'
        ]);

        $fileManager = $this->createMock(FileManager::class);
        $fileManager
            ->expects(self::exactly(2))
            ->method('load')
            ->with('fs', 'filePath')
            ->willReturn($file)
        ;
        $fileManager
            ->expects(self::exactly(2))
            ->method('exists')
            ->with($file)
            ->willReturnOnConsecutiveCalls(true, false)
        ;

        $configuration = new FilePropertyConfiguration(
            TestEntity::class,
            'file',
            'fs',
            'filePath',
            'prefix',
            true
        );

        $testEntity = new TestEntity(null);
        $testEntity->setFilePath('filePath');

        $configurationResolver = new FilePropertyConfigurationResolver([$configuration]);
        $fileLoader = new FileLoader($fileManager, $configurationResolver);

        // No exception
        $fileLoader->loadEntityFiles($testEntity);

        // Exception
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(
            'File for filesystem "fs" not found at path "filePath".'
        );
        $fileLoader->loadEntityFiles($testEntity);
    }
}
