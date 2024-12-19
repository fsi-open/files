<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\DirectUpload\Controller;

use function hash;
use function json_encode;

final class LocalUploadSigner
{
    public function __construct(private readonly string $secret, private readonly string $algorithm)
    {
    }

    /**
     * @param array<string, string|array<string, string>> $data
     * @return string
     */
    public function sign(array $data): string
    {
        $data['secret'] = $this->secret;

        return hash($this->algorithm, json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function verify(array $data, string $signature): bool
    {
        $data['secret'] = $this->secret;

        return hash($this->algorithm, json_encode($data, JSON_THROW_ON_ERROR)) === $signature;
    }
}
