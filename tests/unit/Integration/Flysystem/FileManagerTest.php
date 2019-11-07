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
use FSi\Component\Files\Integration\FlySystem\WebFile;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\MountManager;
use PHPUnit\Framework\TestCase;

final class FileManagerTest extends TestCase
{
    public function testRemovingEmptyParentDirectory(): void
    {
        $file = new WebFile('fs', 'some-dir/some-path');

        $fileSystem = $this->createMock(FilesystemInterface::class);
        $fileSystem->expects($this->once())->method('listContents')->with('some-dir')->willReturn([]);
        $fileSystem->expects($this->once())->method('deleteDir')->with('some-dir');

        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->atLeastOnce())->method('getFilesystem')->with('fs')->willReturn($fileSystem);

        $fileManager = new FileManager($mountManager);
        $fileManager->remove($file);
    }

    public function testNotRemovingNonEmptyParentDirectory(): void
    {
        $file = new WebFile('fs', 'some-dir/some-path');

        $fileSystem = $this->createMock(FilesystemInterface::class);

        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->atLeastOnce())->method('getFilesystem')->with('fs')->willReturn($fileSystem);

        $fileSystem->expects($this->once())->method('delete')->with('some-dir/some-path');
        $fileSystem->expects($this->once())->method('listContents')->with('some-dir')->willReturn(['some-other-path']);
        $fileSystem->expects($this->never())->method('deleteDir');

        $fileManager = new FileManager($mountManager);
        $fileManager->remove($file);
    }
}
