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
use DOMElement;
use FSi\Component\Files\Upload\FilePathGenerator;
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use League\Flysystem\MountManager;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Tests\FSi\App\Entity\FileEntity;
use Tests\FSi\FunctionalTester;

use function codecept_data_dir;
use function file_get_contents;

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

        $I->fillField('form_test[temporaryFile][path]', 'temporary/directly_uploaded_file.pdf');
        $directUploadPath = FilePathGenerator::generate('direct_file.pdf', 'direct-file');
        $I->fillField('form_test[directFile][path]', $directUploadPath);
        $I->submitForm('form', [], 'Submit');
        $I->see('File was not uploaded');

        $I->attachFile('Standard file', 'test_pdf.pdf');
        $I->attachFile('Image file', 'test.jpg');
        $I->attachFile('Private file', 'another_test_pdf.pdf');
        $I->attachFile('Removable embedded image', 'test.jpg');
        $I->attachFile('Removable twice embedded image', 'test.jpg');
        /** @var MountManager $mountManager */
        $mountManager = $I->grabService(MountManager::class);
        $testPdfContents = file_get_contents(codecept_data_dir() . 'test_pdf.pdf');
        Assertion::string($testPdfContents);
        $mountManager->write('public://temporary/directly_uploaded_file.pdf', $testPdfContents);
        // field 'form_test[temporaryFile][path]' has been already filled with path
        $mountManager->write("public://{$directUploadPath}", $testPdfContents);
        // field 'form_test[directFile][path]' has been already filled with path

        $I->submitForm('form', [], 'Submit');

        $I->see('test_pdf.pdf', 'a');
        $I->see('directly_uploaded_file.pdf', 'a');
        $I->see('direct_file.pdf', 'a');
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

        $I->dontSee('test_pdf.pdf', 'a');
        $I->see('directly_uploaded_file.pdf', 'a');
        $I->see('direct_file.pdf', 'a');
        $I->dontSee('another_test_pdf.pdf', 'a');
        $I->seeElement('img[alt="test.jpg"]');

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

        $I->checkOption('#form_test_temporaryFile_remove');
        $I->checkOption('#form_test_directFile_remove');
        $I->submitForm('form', [], 'Submit');

        /** @var FileEntity|null $entity */
        $entity = $I->grabEntityFromRepository(FileEntity::class, ['id' => 1]);
        Assertion::notNull($entity);
        Assertion::null($entity->getTemporaryFile());
        Assertion::null($entity->getTemporaryFileKey());
        Assertion::null($entity->getDirectFile());
        Assertion::null($entity->getDirectFileKey());
    }

    public function testMultipleUpload(FunctionalTester $I): void
    {
        $I->amOnPage('/multiple');
        $I->seeResponseCodeIs(200);

        $I->see('Multiple file', 'label');
        $I->seeElement('#multiple_file_files', ['data-test' => 'test']);

        $client = $I->grabClient();
        $crawler = $I->grabCrawler();
        $form = $crawler->filter('form')->form();
        $node = $crawler->filter("#multiple_file_files")->getNode(0);
        Assertion::isInstanceOf($node, DOMElement::class);
        $newField = new FileFormField($node);
        $form->set($newField);
        $fileField = $form['multiple_file[files]'];
        Assertion::isArray($fileField);
        $fileField1 = $fileField[0];
        Assertion::isInstanceOf($fileField1, FileFormField::class);
        $fileField1->upload(codecept_data_dir() . 'test_pdf.pdf');
        $fileField2 = $fileField[1];
        Assertion::isInstanceOf($fileField2, FileFormField::class);
        $fileField2->upload(codecept_data_dir() . 'another_test_pdf.pdf');
        $crawler = $client->submit($form);
        $I->assertEquals(
            'Uploaded 2 files: "test_pdf.pdf", "another_test_pdf.pdf"',
            $crawler->filter('#message')->html()
        );

        $I->submitForm('form', [], 'Submit');
    }
}
