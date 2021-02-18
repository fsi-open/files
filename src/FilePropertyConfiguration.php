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
    private $entityClass;

    /**
     * @var string
     */
    private $filePropertyName;

    /**
     * @var ReflectionProperty
     */
    private $filePropertyReflection;

    /**
     * @var string
     */
    private $fileSystemName;

    /**
     * @var string
     */
    private $pathPropertyName;

    /**
     * @var ReflectionProperty
     */
    private $pathPropertyReflection;

    /**
     * @var string
     */
    private $pathPrefix;

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
            $this->filePropertyReflection = new ReflectionProperty($this->entityClass, $this->filePropertyName);
            $this->filePropertyReflection->setAccessible(true);
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
            $this->pathPropertyReflection = new ReflectionProperty($this->entityClass, $this->pathPropertyName);
            $this->pathPropertyReflection->setAccessible(true);
        }

        return $this->pathPropertyReflection;
    }

    public function getPathPrefix(): string
    {
        return $this->pathPrefix;
    }
}
