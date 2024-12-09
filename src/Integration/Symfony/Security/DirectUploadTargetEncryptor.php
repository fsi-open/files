<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Security;

use FSi\Component\Files\FilePropertyConfiguration;
use FSi\Component\Files\FilePropertyConfigurationResolver;
use RuntimeException;

use function base64_encode;
use function openssl_cipher_iv_length;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;

final class DirectUploadTargetEncryptor
{
    public const CIPHER_ALGO = 'AES-256-CBC';

    private FilePropertyConfigurationResolver $filePropertyConfigurationResolver;
    private string $secret;

    public function __construct(FilePropertyConfigurationResolver $filePropertyConfigurationResolver, string $secret)
    {
        $this->filePropertyConfigurationResolver = $filePropertyConfigurationResolver;
        $this->secret = $secret;
    }

    public function encrypt(string $entityClass, string $fileProperty): string
    {
        $filePropertyConfiguration = $this->filePropertyConfigurationResolver->resolveFileProperty(
            $entityClass,
            $fileProperty
        );

        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        if (false === is_int($ivLength) || 1 > $ivLength) {
            throw new RuntimeException('Invalid IV length');
        }
        $iv = random_bytes($ivLength);
        $encryptedTarget = openssl_encrypt(
            $filePropertyConfiguration->getEntityClass() . '|' . $filePropertyConfiguration->getFilePropertyName(),
            self::CIPHER_ALGO,
            $this->secret,
            0,
            $iv
        );

        return $encryptedTarget . '.' . base64_encode($iv);
    }

    public function decrypt(string $encryptedTarget): FilePropertyConfiguration
    {
        $parts = explode('.', $encryptedTarget);
        if (2 !== count($parts)) {
            throw new RuntimeException('Invalid encrypted target');
        }

        $target = openssl_decrypt(
            $parts[0],
            self::CIPHER_ALGO,
            $this->secret,
            0,
            base64_decode($parts[1])
        );
        if (false === is_string($target)) {
            throw new RuntimeException('Invalid encrypted target');
        }

        [$entityClass, $fileProperty] = explode('|', $target);

        return $this->filePropertyConfigurationResolver->resolveFileProperty($entityClass, $fileProperty);
    }
}
