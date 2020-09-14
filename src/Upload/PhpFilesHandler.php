<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Upload;

use FSi\Component\Files\UploadedWebFile;
use GuzzleHttp\Psr7\Stream;
use RuntimeException;
use const UPLOAD_ERR_NO_FILE;
use function array_filter;
use function array_key_exists;
use function array_map;
use function count;
use function fopen;
use function is_array;

final class PhpFilesHandler
{
    private const UPLOADED_FILE_KEYS = ['error', 'name', 'size', 'tmp_name', 'type'];

    /**
     * @var FileFactory
     */
    private $fileFactory;

    public function __construct(FileFactory $fileFactory)
    {
        $this->fileFactory = $fileFactory;
    }

    /**
     * @return array<UploadedWebFile|array<UploadedWebFile>>
     */
    public function readFiles(): array
    {
        $phpFiles = array_map([$this, 'fixPhpFilesArray'], $_FILES);

        if (0 === count($phpFiles)) {
            return [];
        }

        $webFiles = array_map(function (array $file) {
            return $this->transformToWebFile($file);
        }, $phpFiles);

        return array_filter($webFiles);
    }

    /**
     * @param array<mixed>|array{error: string, name: string, type: string, tmp_name: string, size: string} $file
     * @return null|UploadedWebFile|array<string, UploadedWebFile|mixed>
     */
    private function transformToWebFile(array $file)
    {
        if (0 === count($file)) {
            return null;
        }

        if (false === $this->hasMatchingArrayKeys($file)) {
            $nestedFiles = $this->findNestedFiles($file);
            if (0 === count($nestedFiles)) {
                return null;
            }

            return array_map([$this, 'transformToWebFile'], $nestedFiles);
        }

        if (UPLOAD_ERR_NO_FILE === $file['error']) {
            return null;
        }

        $stream = fopen($file['tmp_name'], 'r');
        if (false === $stream) {
            throw new RuntimeException(sprintf('Unable to read file "%s"', $file['tmp_name']));
        }

        return $this->fileFactory->create(
            new Stream($stream),
            $file['name'],
            $file['type'],
            $file['size'],
            $file['error']
        );
    }

    /**
     * Fixes a malformed PHP $_FILES array.
     *
     * PHP has a bug that the format of the $_FILES array differs, depending on
     * whether the uploaded file fields had normal field names or array-like
     * field names ("normal" vs. "parent[child]").
     *
     * This method fixes the array to look like the "normal" $_FILES array.
     *
     * It's safe to pass an already converted array, in which case this method
     * just returns the original array unmodified.
     *
     * @see \Symfony\Component\HttpFoundation\FileBag
     * @param array{
     *   error: string|array<string>,
     *   name?: string|array<string|null>,
     *   type: string|array<string>,
     *   tmp_name: string|array<string>,
     *   size: string|array<string>
     * } $data
     * @return array<mixed>|array<array{error: string, name: string, type: string, tmp_name: string, size: string}>
     */
    protected function fixPhpFilesArray(array $data): array
    {
        if (false === $this->hasMatchingArrayKeys($data)
            || false === array_key_exists('name', $data)
            || false === is_array($data['name'])
        ) {
            return $data;
        }

        $files = [];
        foreach ($data['name'] as $key => $name) {
            if ('' === $name || null === $name) {
                continue;
            }

            $files[$key] = $this->fixPhpFilesArray([
                'error' => $data['error'][$key],
                'name' => $name,
                'type' => $data['type'][$key],
                'tmp_name' => $data['tmp_name'][$key],
                'size' => $data['size'][$key],
            ]);
        }

        return $files;
    }

    /**
     * @param array<string, array<string, string|array<string>>> $files
     * @return array<string, array<string, string|array<string>>>
     */
    private function findNestedFiles(array $files): array
    {
        return array_filter($files, function (array $file): bool {
            return $this->hasMatchingArrayKeys($file);
        });
    }

    /**
     * @param array<string, array<int, string|null>|string> $file
     * @return bool
     */
    private function hasMatchingArrayKeys(array $file): bool
    {
        $keys = array_keys($file);
        sort($keys);

        return self::UPLOADED_FILE_KEYS === $keys;
    }
}
