<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\DirectUpload\Controller;

use Tests\FSi\FunctionalTester;

final class DirectUploadControllerCest
{
    public function testTemporaryUploadParams(FunctionalTester $I): void
    {
        $I->sendAjaxPostRequest('/upload/params', [
            'fileSystemName' => 'public',
            'fileSystemPrefix' => 'temporary',
            'filename' => 'test.pdf',
            'contentType' => 'application/pdf',
        ]);

        $response = $I->grabClient()->getResponse();
        $I->seeResponseCodeIs(200);
    }
}
