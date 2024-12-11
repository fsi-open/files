<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Module;

use Assert\Assertion;
use Codeception\Lib\Connector\Symfony as SymfonyConnector;
use Codeception\Module;
use Codeception\Module\Symfony;
use Codeception\TestInterface;
use FSi\Component\Files\Upload\FileFactory;
use FSi\Component\Files\Upload\PhpFilesHandler;
use Symfony\Component\DomCrawler\Crawler;

final class UploadModule extends Module
{
    private Symfony $symfony;

    /**
     * @phpcs:disable
     */
    public function _before(TestInterface $test): void
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        $this->symfony = $symfony;
    }

    /**
     * @return array<\FSi\Component\Files\UploadedWebFile|array<\FSi\Component\Files\UploadedWebFile>>
     */
    public function grabUploadedPhpFiles(): array
    {
        /** @var PhpFilesHandler $phpFilesHandler */
        $phpFilesHandler = $this->symfony->grabService(PhpFilesHandler::class);
        return $phpFilesHandler->readFiles();
    }

    public function grabFileFactory(): FileFactory
    {
        /** @var FileFactory $fileFactory */
        $fileFactory = $this->symfony->grabService('test.' . FileFactory::class);
        return $fileFactory;
    }

    public function grabClient(): SymfonyConnector
    {
        Assertion::notNull($this->symfony->client);

        return $this->symfony->client;
    }

    public function grabCrawler(): Crawler
    {
        return $this->grabClient()->getCrawler();
    }
}
