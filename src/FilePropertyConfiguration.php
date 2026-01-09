<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files;

use FSi\Component\Files\Exception\InvalidFieldTypeException;
use FSi\Component\Files\Exception\InvalidUnionFieldTypeException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

use function count;
use function get_parent_class;
use function ltrim;

use const PHP_VERSION_ID;

final class FilePropertyConfiguration
{
    /**
     * @var class-string<object>
     */
    private string $entityClass;
    private string $filePropertyName;
    private string $fileSystemName;
    private string $pathPropertyName;
    private string $pathPrefix;
    private ?ReflectionProperty $filePropertyReflection;
    private ?ReflectionProperty $pathPropertyReflection;

    /**
     * @param class-string<object> $entityClass
     */
    public function __construct(
        string $entityClass,
        string $filePropertyName,
        string $fileSystemName,
        string $pathPropertyName,
        string $pathPrefix
    ) {
        $this->entityClass = $entityClass;
        $this->filePropertyName = $filePropertyName;
        $this->fileSystemName = $fileSystemName;
        $this->pathPropertyName = $pathPropertyName;
        $this->pathPrefix = $pathPrefix;
        $this->filePropertyReflection = null;
        $this->pathPropertyReflection = null;

        $this->assertPropertyType($this->getFilePropertyReflection(), WebFile::class);
        $this->assertPropertyType($this->getPathPropertyReflection(), 'string');
    }

    /**
     * @return class-string<object>
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getFilePropertyName(): string
    {
        return $this->filePropertyName;
    }

    public function getFilePropertyReflection(): ReflectionProperty
    {
        if (null === $this->filePropertyReflection) {
            $this->filePropertyReflection = $this->createPropertyReflection(
                $this->entityClass,
                $this->filePropertyName
            );
        }

        return $this->filePropertyReflection;
    }

    public function getFileSystemName(): string
    {
        return $this->fileSystemName;
    }

    public function getPathPropertyName(): string
    {
        return $this->pathPropertyName;
    }

    public function getPathPropertyReflection(): ReflectionProperty
    {
        if (null === $this->pathPropertyReflection) {
            $this->pathPropertyReflection = $this->createPropertyReflection(
                $this->entityClass,
                $this->pathPropertyName
            );
        }

        return $this->pathPropertyReflection;
    }

    public function getPathPrefix(): string
    {
        return $this->pathPrefix;
    }

    private function assertPropertyType(ReflectionProperty $propertyReflection, string $expectedType): void
    {
        if (false === $propertyReflection->hasType()) {
            return;
        }

        $property = $propertyReflection->getName();

        /** @var ReflectionNamedType|ReflectionUnionType|ReflectionType $propertyTypeReflection */
        $propertyTypeReflection = $propertyReflection->getType();
        if (true === $propertyTypeReflection instanceof ReflectionNamedType) {
            $actualType = $this->sanitizePropertyType($propertyTypeReflection->getName());
            if ($expectedType !== $actualType) {
                throw new InvalidFieldTypeException($this->entityClass, $property, $expectedType, $actualType);
            }
        } elseif (true === $propertyTypeReflection instanceof ReflectionUnionType) {
            /** @var array<ReflectionNamedType> $unionTypes */
            $unionTypes = $propertyTypeReflection->getTypes();
            $this->assertUnionTypeContainsType(
                $unionTypes,
                $expectedType,
                $property
            );
        } else {
            throw new RuntimeException("Unable to read property type from \"{$property}\"");
        }
    }

    /**
     * @param array<ReflectionNamedType> $types
     */
    private function assertUnionTypeContainsType(array $types, string $expectedType, string $property): void
    {
        $matches = array_filter(
            $types,
            fn(ReflectionNamedType $propertyTypeReflection): bool
                => $this->sanitizePropertyType($propertyTypeReflection->getName()) === $expectedType
        );

        if (1 !== count($matches)) {
            throw new InvalidUnionFieldTypeException($this->entityClass, $property, $expectedType);
        }
    }

    /**
     * @param class-string $entityClass
     */
    private function createPropertyReflection(string $entityClass, string $property): ReflectionProperty
    {
        $checkedClass = $entityClass;
        while (false === $this->classHasProperty($checkedClass, $property)) {
            $parentClass = get_parent_class($checkedClass);
            if (false === $parentClass) {
                break;
            }

            $checkedClass = $parentClass;
        }

        $propertyReflection = new ReflectionProperty($checkedClass, $property);
        if (PHP_VERSION_ID < 80500) {
            $propertyReflection->setAccessible(true);
        }

        return $propertyReflection;
    }

    /**
     * @param class-string $className
     */
    private function classHasProperty(string $className, string $propertyName): bool
    {
        return (new ReflectionClass($className))->hasProperty($propertyName);
    }

    private function sanitizePropertyType(string $propertyType): string
    {
        return ltrim($propertyType, '?');
    }
}
