<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Upload;

use FSi\Component\Files\WebFile;
use Tests\FSi\App\Entity\ChildFileEntity;
use Tests\FSi\FunctionalTester;

use function codecept_data_dir;

final class InheritanceUploadCest
{
    public function testUploadingFileToParentProperty(FunctionalTester $I): void
    {
        $id = $I->haveInRepository(ChildFileEntity::class, [
            'file' => $I->grabFileFactory()->createFromPath(codecept_data_dir('test.jpg'))
        ]);

        /** @var ChildFileEntity $child */
        $child = $I->grabEntityFromRepository(ChildFileEntity::class, [
            'id' => $id
        ]);

        $I->assertInstanceOf(WebFile::class, $child->getFile());
    }
}
