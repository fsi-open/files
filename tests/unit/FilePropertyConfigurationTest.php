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
use FSi\Component\Files\Exception\FieldWithoutDefaultNullValueException;
use FSi\Component\Files\Exception\InvalidFieldTypeException;
use FSi\Component\Files\FilePropertyConfiguration;
use ReflectionException;
use Tests\FSi\Component\Files\Entity\TestEntity;

final class FilePropertyConfigurationTest extends Unit
{
    public function testNonExistantFileProperty(): void
    {
        self::expectException(ReflectionException::class);
        self::expectExceptionMessage('Property Tests\FSi\Component\Files\Entity\TestEntity::$image does not exist');

        new FilePropertyConfiguration(
            TestEntity::class,
            'image',
            'filesystem',
            'imagePath',
            'test'
        );
    }

    public function testNonExistantPathProperty(): void
    {
        self::expectException(ReflectionException::class);
        self::expectExceptionMessage('Property Tests\FSi\Component\Files\Entity\TestEntity::$imagePath does not exist');

        new FilePropertyConfiguration(
            TestEntity::class,
            'file',
            'filesystem',
            'imagePath',
            'test'
        );
    }

    public function testIncorrectFilePropertyType(): void
    {
        self::expectException(InvalidFieldTypeException::class);
        self::expectExceptionMessage(
            'Field "filePath" of class "Tests\FSi\Component\Files\Entity\TestEntity"'
            . ' should be of type "FSi\Component\Files\WebFile", but it is "string"'
        );

        new FilePropertyConfiguration(
            TestEntity::class,
            'filePath',
            'filesystem',
            'file',
            'test'
        );
    }

    public function testIncorrectPathPropertyType(): void
    {
        self::expectException(InvalidFieldTypeException::class);
        self::expectExceptionMessage(
            'Field "file" of class "Tests\FSi\Component\Files\Entity\TestEntity"'
            . ' should be of type "string", but it is "FSi\Component\Files\WebFile"'
        );

        new FilePropertyConfiguration(
            TestEntity::class,
            'file',
            'filesystem',
            'file',
            'test'
        );
    }

    /**
     * @env php8
     * @return void
     */
    public function testFilePropertyTypeNoDefaultValue(): void
    {
        self::expectException(FieldWithoutDefaultNullValueException::class);
        self::expectExceptionMessage(
            'Field "file1" of class "Tests\FSi\Component\Files\Entity\TestEntity"'
            . ' does not have a default null value'
        );

        new FilePropertyConfiguration(
            TestEntity::class,
            'file1',
            'filesystem',
            'filePath',
            'test'
        );
    }

    /**
     * @env php8
     * @return void
     */
    public function testPathPropertyTypeNoDefaultValue(): void
    {
        self::expectException(FieldWithoutDefaultNullValueException::class);
        self::expectExceptionMessage(
            'Field "filePath1" of class "Tests\FSi\Component\Files\Entity\TestEntity"'
            . ' does not have a default null value'
        );

        new FilePropertyConfiguration(
            TestEntity::class,
            'file',
            'filesystem',
            'filePath1',
            'test'
        );
    }
}
