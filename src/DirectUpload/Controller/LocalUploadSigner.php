<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Controller;

use function hash_equals;
use function hash_hmac;
use function json_encode;

final class LocalUploadSigner
{
    public function __construct(private readonly string $secret, private readonly string $algorithm)
    {
    }

    /**
     * @param array<string|array<string>> $data
     * @return string
     */
    public function sign(array $data): string
    {
        return hash_hmac($this->algorithm, json_encode($data, JSON_THROW_ON_ERROR), $this->secret);
    }

    /**
     * @param array<string|array<string>> $data
     * @param string $signature
     * @return bool
     */
    public function verify(array $data, string $signature): bool
    {
        return hash_equals(
            hash_hmac($this->algorithm, json_encode($data, JSON_THROW_ON_ERROR), $this->secret),
            $signature
        );
    }
}
