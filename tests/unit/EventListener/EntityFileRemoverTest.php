<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\EventListener;

use FSi\Component\Files\EventListener\EntityFileLoader;
use FSi\Component\Files\EventListener\EntityFileRemover;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\Integration\FlySystem\FilePropertyConfigurationResolver;
use FSi\Component\Files\Integration\FlySystem\WebFile;
use PHPUnit\Framework\TestCase;

final class EntityFileRemoverTest extends TestCase
{
    public function testRemoving(): void
    {
        $file = new WebFile('fs', 'some-path');

        $fileManager = $this->createMock(FileManager::class);
        $fileManager->expects($this->once())->method('remove')->with($file);

        $remover = new EntityFileRemover(
            new FilePropertyConfigurationResolver([]),
            $fileManager,
            $this->createMock(EntityFileLoader::class)
        );
        $remover->add($file);

        $remover->flush();
    }
}
