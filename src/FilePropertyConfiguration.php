<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files;

use ReflectionProperty;

final class FilePropertyConfiguration
{
    /**
     * @var class-string
     */
    private string $entityClass;
    private string $filePropertyName;
    private string $fileSystemName;
    private string $pathPropertyName;
    private string $pathPrefix;
    private ?ReflectionProperty $filePropertyReflection;
    private ?ReflectionProperty $pathPropertyReflection;

    /**
     * @param class-string $entityClass
     * @param string $filePropertyName
     * @param string $fileSystemName
     * @param string $pathPropertyName
     * @param string $pathPrefix
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
    }

    /**
     * @return class-string
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

    /**
     * @param class-string $entityClass
     * @param string $property
     * @return ReflectionProperty
     */
    private function createPropertyReflection(string $entityClass, string $property): ReflectionProperty
    {
        $propertyReflection = new ReflectionProperty($entityClass, $property);
        $propertyReflection->setAccessible(true);

        return $propertyReflection;
    }
}
