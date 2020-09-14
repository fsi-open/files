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
use Codeception\TestInterface;
use FSi\Component\Files\Upload\PhpFilesHandler;

final class UploadModule extends Module
{
    /**
     * @var Symfony
     */
    private $symfony;

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
        $phpFilesHandler = $this->symfony->_getContainer()->get(PhpFilesHandler::class);
        return $phpFilesHandler->readFiles();
    }
}
