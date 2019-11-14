<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\Entity;

use FSi\Component\Files\Entity\FileLoader;
use FSi\Component\Files\Entity\FileRemover;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\Integration\FlySystem\WebFile;
use PHPUnit\Framework\TestCase;

final class FileRemoverTest extends TestCase
{
    public function testRemoving(): void
    {
        $file = new WebFile('fs', 'path-prefix/directory/subdirectory/file');

        $fileManager = $this->createMock(FileManager::class);
        $fileManager->expects($this->once())->method('remove')->with($file);
        $fileManager->expects($this->exactly(2))
            ->method('removeDirectoryIfEmpty')
            ->withConsecutive(
                ['fs', 'path-prefix/directory/subdirectory'],
                ['fs', 'path-prefix/directory']
            )
            ->willReturnOnConsecutiveCalls(true, false)
        ;

        $configurationResolver = new FilePropertyConfigurationResolver([]);
        $remover = new FileRemover(
            $configurationResolver,
            $fileManager,
            new FileLoader($fileManager, $configurationResolver)
        );

        $remover->add('path-prefix', $file);

        $remover->flush();
    }
}
