<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Integration\Symfony\Form;

use Assert\Assertion;
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use Tests\FSi\App\Entity\FileEntity;
use Tests\FSi\FunctionalTester;

final class WebFileTypeCest
{
    public function testUpload(FunctionalTester $I): void
    {
        $I->haveInRepository(FileEntity::class, ['id' => 1]);

        $I->amOnPage('/symfony');
        $I->seeResponseCodeIs(200);

        $I->see('Standard file', 'label');
        $I->see('Image file', 'label');
        $I->see('Private file', 'label');
        $I->see('Removable embedded image', 'label');
        $I->see('Removable twice embedded image', 'label');
        $I->attachFile('Standard file', 'test_pdf.pdf');
        $I->attachFile('Image file', 'test.jpg');
        $I->attachFile('Private file', 'another_test_pdf.pdf');
        $I->attachFile('Removable embedded image', 'test.jpg');
        $I->attachFile('Removable twice embedded image', 'test.jpg');

        $I->submitForm('form', [], 'Submit');

        $I->see('test_pdf.pdf', 'a');
        $I->dontSee('another_test_pdf.pdf', 'a');
        $I->seeElement('img[alt="test.jpg"]');

        /** @var FileEntity|null $entity */
        $entity = $I->grabEntityFromRepository(FileEntity::class, ['id' => 1]);
        Assertion::notNull($entity);

        $I->assertInstanceOf(WebFile::class, $entity->getFile());
        $I->assertNotInstanceOf(UploadedWebFile::class, $entity->getFile());

        $I->assertInstanceOf(WebFile::class, $entity->getAnotherFile());
        $I->assertNotInstanceOf(UploadedWebFile::class, $entity->getAnotherFile());

        $I->assertInstanceOf(WebFile::class, $entity->getPrivateFile());
        $I->assertNotInstanceOf(UploadedWebFile::class, $entity->getPrivateFileKey());

        $embeddedFile = $entity->getEmbeddedFile();
        Assertion::notNull($embeddedFile);
        $I->assertInstanceOf(WebFile::class, $embeddedFile->file);
        $I->assertNotInstanceOf(UploadedWebFile::class, $embeddedFile->file);

        $twiceEmbeddedFile = $embeddedFile->embeddedFile;
        Assertion::notNull($twiceEmbeddedFile);
        $I->assertInstanceOf(WebFile::class, $twiceEmbeddedFile->file);
        $I->assertNotInstanceOf(UploadedWebFile::class, $twiceEmbeddedFile->file);

        $I->amOnPage('/symfony');
        $I->checkOption('#form_test_file_remove');
        $I->checkOption('#form_test_embeddedFile_file_remove');
        $I->checkOption('#form_test_embeddedFile_embeddedFile_file_remove');
        $I->submitForm('form', [], 'Submit');

        /** @var FileEntity|null $entity */
        $entity = $I->grabEntityFromRepository(FileEntity::class, ['id' => 1]);
        Assertion::notNull($entity);
        $I->assertNull($entity->getFile());

        $I->assertInstanceOf(WebFile::class, $entity->getAnotherFile());
        $I->assertNotInstanceOf(UploadedWebFile::class, $entity->getAnotherFile());

        $embeddedFile = $entity->getEmbeddedFile();
        Assertion::notNull($embeddedFile);
        $I->assertNull($embeddedFile->file);

        $twiceEmbeddedFile = $embeddedFile->embeddedFile;
        Assertion::notNull($twiceEmbeddedFile);
        $I->assertNull($twiceEmbeddedFile->file);
    }
}
