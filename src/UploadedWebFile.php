<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files;

interface UploadedWebFile extends WebFile
{
    public function getStream();
    public function getOriginalName(): string;
    public function getMimeType(): string;
    public function getSize(): int;
    public function getError(): ?int;
}
