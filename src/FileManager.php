<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files;

interface FileManager
{
    public function create(string $fileSystemPrefix, string $path, string $contents): void;
    public function exists(WebFile $file): bool;
    public function filename(WebFile $file): string;
    public function contents(WebFile $file): string;
    public function remove(WebFile $file): void;
    public function readStream(WebFile $file);
    public function writeStream(string $fileSystemPrefix, string $path, $stream): void;
}
