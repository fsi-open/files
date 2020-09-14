<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Module;

use Codeception\Module;
use Codeception\Module\Symfony;
use DirectoryIterator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use function file_exists;
use function rmdir;
use function sprintf;
use function unlink;

final class DbModule extends Module
{
    /**
     * @param array<string, mixed> $settings
     * @phpcs:disable
     */
    public function _beforeSuite($settings = []): void
    {
        $this->removeDatabaseFile();
        $this->clearUploadedTestFiles();

        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');

        /** @var EntityManagerInterface $manager */
        $manager = $symfony->_getEntityManager();

        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
    }

    /**
     * @phpcs:disable
     */
    public function _afterSuite(): void
    {
        $this->removeDatabaseFile();
        $this->clearUploadedTestFiles();
    }

    private function removeDatabaseFile(): void
    {
        $filePath = sprintf('%s/../project/var/data.sqlite', __DIR__);
        if (false === file_exists($filePath)) {
            return;
        }

        unlink($filePath);
    }

    private function clearUploadedTestFiles(): void
    {
        $public = sprintf('%s/../project/public/files', __DIR__);
        if (true === file_exists($public)) {
            $this->clearDirectory($public);
        }

        $otherPublic = sprintf('%s/../project/public/other_files', __DIR__);
        if (true === file_exists($otherPublic)) {
            $this->clearDirectory($otherPublic);
        }

    }

    private function clearDirectory(string $path): void
    {
        $iterator = new DirectoryIterator($path);
        foreach ($iterator as $file) {
            if (true === $file->isDot()) {
                continue;
            }

            if (true === $file->isDir()) {
                $this->clearDirectory($file->getPathname());
            } elseif (true === $file->isFile()) {
                unlink($file->getPathname());
            }
        }

        rmdir($path);
    }
}
