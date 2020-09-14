<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\Integration\Flysystem;

use FSi\Component\Files\Integration\FlySystem\FileManager;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\MountManager;
use PHPUnit\Framework\TestCase;

final class FileManagerTest extends TestCase
{
    public function testNotRemovingNonEmptyDirectory(): void
    {
        $fileSystem = $this->createMock(FilesystemInterface::class);
        $fileSystem->expects($this->once())->method('listContents')->with('parent/child')->willReturn(['a file']);
        $fileSystem->expects($this->never())->method('deleteDir');

        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->atLeastOnce())->method('getFilesystem')->with('fs')->willReturn($fileSystem);

        (new FileManager($mountManager))->removeDirectoryIfEmpty('fs', 'parent/child');
    }

    public function testRemovingEmptyDirectory(): void
    {
        $fileSystem = $this->createMock(FilesystemInterface::class);
        $fileSystem->expects($this->once())->method('listContents')->with('parent/child')->willReturn([]);
        $fileSystem->expects($this->once())->method('deleteDir')->with('parent/child')->willReturn(true);

        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->atLeastOnce())->method('getFilesystem')->with('fs')->willReturn($fileSystem);

        (new FileManager($mountManager))->removeDirectoryIfEmpty('fs', 'parent/child');
    }

    /**
     * @dataProvider provideEmptyOrRootDirectoryPaths()
     */
    public function testNotRemovingEmptyOrRootDirectories(string $path): void
    {
        $fileSystem = $this->createMock(FilesystemInterface::class);
        $fileSystem->expects($this->never())->method('listContents');
        $fileSystem->expects($this->never())->method('deleteDir');

        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->atLeastOnce())->method('getFilesystem')->with('fs')->willReturn($fileSystem);

        $this->assertFalse(
            (new FileManager($mountManager))->removeDirectoryIfEmpty('fs', $path)
        );
    }

    /**
     * @return array<array<string>>
     */
    public function provideEmptyOrRootDirectoryPaths(): array
    {
        return [[''], ['/'], ['.']];
    }
}
