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
        $file = new WebFile('fs', 'path-prefix/0/1/2/3/some-path');

        $fileSystem = $this->createMock(FilesystemInterface::class);
        $fileSystem->expects($this->exactly(4))
            ->method('listContents')
            ->withConsecutive(['path-prefix/0/1/2/3'], ['path-prefix/0/1/2'], ['path-prefix/0/1'], ['path-prefix/0'])
            ->willReturnOnConsecutiveCalls([], [], [], [])
        ;
        $fileSystem->expects($this->exactly(4))
            ->method('deleteDir')
            ->withConsecutive(['path-prefix/0/1/2/3'], ['path-prefix/0/1/2'], ['path-prefix/0/1'], ['path-prefix/0'])
        ;

        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->atLeastOnce())->method('getFilesystem')->with('fs')->willReturn($fileSystem);

        $fileManager = new FileManager($mountManager);
        $fileManager->removeFileEmptyParentDirectories('path-prefix', $file);
    }

    public function testNotRemovingNonEmptyParentDirectory(): void
    {
        $file = new WebFile('fs', 'path-prefix/0/1/2/3/some-path');

        $fileSystem = $this->createMock(FilesystemInterface::class);
        $fileSystem->expects($this->exactly(4))
            ->method('listContents')
            ->withConsecutive(['path-prefix/0/1/2/3'], ['path-prefix/0/1/2'], ['path-prefix/0/1'], ['path-prefix/0'])
            ->willReturnOnConsecutiveCalls([], [], [], ['a_file'])
        ;
        $fileSystem->expects($this->exactly(3))
            ->method('deleteDir')
            ->withConsecutive(['path-prefix/0/1/2/3'], ['path-prefix/0/1/2'], ['path-prefix/0/1'])
        ;

        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->atLeastOnce())->method('getFilesystem')->with('fs')->willReturn($fileSystem);

        $fileManager = new FileManager($mountManager);
        $fileManager->removeFileEmptyParentDirectories('path-prefix', $file);
    }
}
