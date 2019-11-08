<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\Component\Files\Integration\Symfony\Form;

use FSi\Tests\FunctionalTester;

final class WebFileCest
{
    public function testUpload(FunctionalTester $I): void
    {
        $I->amOnPage('/symfony');
        $I->seeResponseCodeIs(200);

        $I->see('Single file', 'label');
        $I->attachFile('Single file', 'test_pdf.pdf');

        $I->submitForm('form', [], 'Submit');

        $I->see('Uploaded file "test_pdf.pdf"');
    }
}
