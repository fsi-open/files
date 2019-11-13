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
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

final class DbModule extends Module
{
    /**
     * @var SchemaTool
     */
    private $schemaTool;

    /**
     * @var ClassMetadata[]
     */
    private $allMetadata;

    /**
     * @phpcs:disable
     */
    public function _before(TestInterface $test)
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');

        /** @var EntityManagerInterface $manager */
        $manager = $symfony->_getEntityManager();
        $this->allMetadata = $manager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($manager);
        $this->schemaTool = $schemaTool;
        $this->schemaTool->createSchema($this->allMetadata);
    }

    /**
     * @phpcs:disable
     */
    public function _after(TestInterface $test)
    {
        $this->dropSchema();
    }

    private function dropSchema(): void
    {
        $databaseFile = sprintf('%s/../project/var/data.sqlite', __DIR__);
        if (false === file_exists($databaseFile)) {
            return;
        }

        $this->schemaTool->dropSchema($this->allMetadata);
        $this->schemaTool->dropDatabase();
        unlink($databaseFile);
    }
}
