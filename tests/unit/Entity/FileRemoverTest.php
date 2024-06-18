<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Entity;

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
        $matcher = $this->exactly(2);
        $fileManager->expects($matcher)
            ->method('removeDirectoryIfEmpty')
            ->willReturnCallback(function (string $fileSystemName, string $path) use ($matcher) {
                $this->assertEquals('fs', $fileSystemName);
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals('path-prefix/directory/subdirectory', $path),
                    2 => $this->assertEquals('path-prefix/directory', $path),
                    default => $this->fail('Unexpected invocation'),
                };

                return match ($matcher->numberOfInvocations()) {
                    1 => true,
                    2 => false,
                    default => $this->fail('Unexpected invocation'),
                };
            })
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
