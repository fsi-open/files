<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload;

use RuntimeException;

use function array_key_exists;

final class AdapterRegistry
{
    /**
     * @param array<string, DirectUploadAdapter> $adapters
     */
    public function __construct(private array $adapters)
    {
    }

    public function getAdapter(string $filesystem): DirectUploadAdapter
    {
        if (false === array_key_exists($filesystem, $this->adapters)) {
            throw new RuntimeException("Direct upload adapter for filesystem \"{$filesystem}\" not found.");
        }

        return $this->adapters[$filesystem];
    }
}
