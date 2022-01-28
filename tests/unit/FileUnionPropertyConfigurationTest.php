<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files;

use Codeception\Test\Unit;
use FSi\Component\Files\Exception\InvalidUnionFieldTypeException;
use FSi\Component\Files\FilePropertyConfiguration;
use Tests\FSi\Component\Files\Entity\UnionTestEntity;

final class FileUnionPropertyConfigurationTest extends Unit
{
    /**
     * @env php8
     * @return void
     */
    public function testUnionProperties(): void
    {
        $configuration = new FilePropertyConfiguration(
            UnionTestEntity::class,
            'fileUnionType',
            'filesystem',
            'scalarUnionType',
            'test'
        );

        // Check anything
        self::assertSame(UnionTestEntity::class, $configuration->getEntityClass());
    }

    /**
     * @env php8
     * @return void
     */
    public function testIncorrectUnionFilePropertyType(): void
    {
        self::expectException(InvalidUnionFieldTypeException::class);
        self::expectExceptionMessage(
            'Union field "scalarUnionType" of class "Tests\FSi\Component\Files\Entity\UnionTestEntity"'
            . ' does not contain the type "FSi\Component\Files\WebFile"'
        );

        new FilePropertyConfiguration(
            UnionTestEntity::class,
            'scalarUnionType',
            'filesystem',
            'filePath',
            'test'
        );
    }

    /**
     * @env php8
     * @return void
     */
    public function testIncorrectUnionFilePathPropertyType(): void
    {
        self::expectException(InvalidUnionFieldTypeException::class);
        self::expectExceptionMessage(
            'Union field "fileUnionType" of class "Tests\FSi\Component\Files\Entity\UnionTestEntity"'
            . ' does not contain the type "string"'
        );

        new FilePropertyConfiguration(
            UnionTestEntity::class,
            'file',
            'filesystem',
            'fileUnionType',
            'test'
        );
    }
}
