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
use FSi\Component\Files\WebFile;
use FSi\Tests\App\Entity\FileEntity;
use FSi\Tests\FunctionalTester;

final class WebFileTypeCest
{
    public function testUpload(FunctionalTester $I): void
    {
        $I->haveInRepository(FileEntity::class, []);

        $I->amOnPage('/symfony');
        $I->seeResponseCodeIs(200);

        $I->see('File', 'label');
        $I->see('Another file', 'label');
        $I->attachFile('File', 'test_pdf.pdf');
        $I->attachFile('Another file', 'test_pdf.pdf');

        $I->submitForm('form', [], 'Submit');

        $I->see('Uploaded file "test_pdf.pdf"');

        /** @var FileEntity|null $entity */
        $entity = $I->grabEntityFromRepository(FileEntity::class);
        Assertion::notNull($entity);
        $I->assertInstanceOf(WebFile::class, $entity->getFile());
        $I->assertInstanceOf(WebFile::class, $entity->getAnotherFile());
    }
}
