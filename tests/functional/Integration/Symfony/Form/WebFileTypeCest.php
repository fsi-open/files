<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\Integration\Symfony\Form;

use Assert\Assertion;
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use FSi\Tests\App\Entity\FileEntity;
use FSi\Tests\FunctionalTester;

final class WebFileTypeCest
{
    public function testUpload(FunctionalTester $I): void
    {
        $I->haveInRepository(FileEntity::class, ['id' => 1]);

        $I->amOnPage('/symfony');
        $I->seeResponseCodeIs(200);

        $I->see('Standard file', 'label');
        $I->see('Image file', 'label');
        $I->attachFile('Standard file', 'test_pdf.pdf');
        $I->attachFile('Image file', 'test.jpg');

        $I->submitForm('form', [], 'Submit');

        $I->see('test_pdf.pdf', 'a');
        $I->seeElement('img[alt="test.jpg"]');

        /** @var FileEntity|null $entity */
        $entity = $I->grabEntityFromRepository(FileEntity::class, ['id' => 1]);
        Assertion::notNull($entity);

        $I->assertInstanceOf(WebFile::class, $entity->getFile());
        $I->assertNotInstanceOf(UploadedWebFile::class, $entity->getFile());

        $I->assertInstanceOf(WebFile::class, $entity->getAnotherFile());
        $I->assertNotInstanceOf(UploadedWebFile::class, $entity->getAnotherFile());

        $I->amOnPage('/symfony');
        $I->checkOption('Remove file');
        $I->submitForm('form', [], 'Submit');

        /** @var FileEntity|null $entity */
        $entity = $I->grabEntityFromRepository(FileEntity::class, ['id' => 1]);
        Assertion::notNull($entity);
        $I->assertNull($entity->getFile());

        $I->assertInstanceOf(WebFile::class, $entity->getAnotherFile());
        $I->assertNotInstanceOf(UploadedWebFile::class, $entity->getAnotherFile());
    }
}
