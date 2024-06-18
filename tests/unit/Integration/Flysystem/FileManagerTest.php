<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Integration\Flysystem;

use Doctrine\Common\Collections\ArrayCollection;
use FSi\Component\Files\Integration\FlySystem\FileManager;
use League\Flysystem\DirectoryListing;
use League\Flysystem\MountManager;
use PHPUnit\Framework\TestCase;

final class FileManagerTest extends TestCase
{
    public function testNotRemovingNonEmptyDirectory(): void
    {
        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->never())->method('deleteDirectory');
        $mountManager
            ->expects($this->once())
            ->method('listContents')
            ->with('fs://parent/child')
            ->willReturn(new DirectoryListing(new ArrayCollection(['a file'])))
        ;

        (new FileManager($mountManager))->removeDirectoryIfEmpty('fs', 'parent/child');
    }

    public function testRemovingEmptyDirectory(): void
    {
        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->once())->method('deleteDirectory')->with('fs://parent/child');
        $mountManager
            ->expects($this->once())
            ->method('listContents')
            ->with('fs://parent/child')
            ->willReturn(new DirectoryListing(new ArrayCollection([])))
        ;

        (new FileManager($mountManager))->removeDirectoryIfEmpty('fs', 'parent/child');
    }

    /**
     * @dataProvider provideEmptyOrRootDirectoryPaths
     */
    public function testNotRemovingEmptyOrRootDirectories(string $path): void
    {
        $mountManager = $this->createMock(MountManager::class);
        $mountManager->expects($this->never())->method('listContents');
        $mountManager->expects($this->never())->method('deleteDirectory');

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
