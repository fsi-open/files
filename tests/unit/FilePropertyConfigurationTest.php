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
use FSi\Component\Files\Exception\InvalidFieldTypeException;
use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\UploadedWebFile;
use ReflectionException;
use Tests\FSi\Component\Files\Entity\TestChildChildEntity;
use Tests\FSi\Component\Files\Entity\TestChildEntity;
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

    public function testPropertyInParentClass(): void
    {
        $propertyConfiguration = new FilePropertyConfiguration(
            TestChildEntity::class,
            'file',
            'filesystem',
            'filePath',
            'test'
        );

        $child = new TestChildEntity(null);
        $file = $this->createMock(UploadedWebFile::class);
        $propertyConfiguration->getFilePropertyReflection()->setValue($child, $file);

        self::assertSame($file, $child->getFile());
    }

    public function testPropertyInParentsParentClass(): void
    {
        $propertyConfiguration = new FilePropertyConfiguration(
            TestChildChildEntity::class,
            'file',
            'filesystem',
            'filePath',
            'test'
        );

        $child = new TestChildChildEntity(null);
        $file = $this->createMock(UploadedWebFile::class);
        $propertyConfiguration->getFilePropertyReflection()->setValue($child, $file);

        self::assertSame($file, $child->getFile());
    }
}
