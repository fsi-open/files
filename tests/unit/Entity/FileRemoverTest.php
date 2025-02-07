<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Entity;

use FSi\Component\Files\Entity\Event\WebFileRemoved;
use FSi\Component\Files\Entity\FileLoader;
use FSi\Component\Files\Entity\FileRemover;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\Integration\FlySystem\WebFile;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tests\FSi\App\Entity\FileEntity;

final class FileRemoverTest extends TestCase
{
    public function testRemoving(): void
    {
        $propertyConfiguration = new FilePropertyConfiguration(
            FileEntity::class,
            'file',
            'fs',
            'filePath',
            'path-prefix'
        );
        $entity = new FileEntity();
        $file = new WebFile('fs', 'path-prefix/directory/subdirectory/file');
        $tempFile = new WebFile('fs', 'temp/01/02/file');

        $fileManager = $this->createMock(FileManager::class);
        $matcher = $this->exactly(2);
        $fileManager->expects($matcher)
            ->method('remove')
            ->with(self::callback(
                fn(WebFile $fileToRemove): bool => match ($matcher->numberOfInvocations()) {
                    1 => $fileToRemove->getFileSystemName() === $file->getFileSystemName()
                        && $fileToRemove->getPath() === $file->getPath(),
                    2 => $fileToRemove->getFileSystemName() === $tempFile->getFileSystemName()
                        && $fileToRemove->getPath() === $tempFile->getPath(),
                    default => $this->fail('Unexpected invocation'),
                }
            ));
        $matcher = $this->exactly(6);
        $fileManager->expects($matcher)
            ->method('removeDirectoryIfEmpty')
            ->willReturnCallback(function (string $fileSystemName, string $path) use ($matcher) {
                $this->assertEquals('fs', $fileSystemName);
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals('path-prefix/directory/subdirectory', $path),
                    2 => $this->assertEquals('path-prefix/directory', $path),
                    3 => $this->assertEquals('temp/01/02', $path),
                    4 => $this->assertEquals('temp/01', $path),
                    5 => $this->assertEquals('temp', $path),
                    6 => $this->assertEquals('.', $path),
                    default => $this->fail('Unexpected invocation'),
                };

                return match ($matcher->numberOfInvocations()) {
                    1, 3, 4, 5 => true,
                    2, 6 => false,
                    default => $this->fail('Unexpected invocation'),
                };
            })
        ;

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            self::callback(
                static fn (WebFileRemoved $event) =>
                    $event->getConfiguration() === $propertyConfiguration
                    && $event->getEntity() === $entity
                    && $event->getFile() === $file
            )
        );

        $configurationResolver = new FilePropertyConfigurationResolver([]);
        $remover = new FileRemover(
            $configurationResolver,
            $fileManager,
            new FileLoader($fileManager, $configurationResolver),
            $eventDispatcher
        );

        $remover->add($propertyConfiguration, $entity, $file);
        $remover->add(null, $entity, $tempFile);

        $remover->flush();
    }
}
