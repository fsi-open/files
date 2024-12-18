<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form\Transformer;

use FSi\Component\Files\FileManager;
use FSi\Component\Files\Upload\FileFactory;
use FSi\Component\Files\WebFile;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * @implements DataTransformerInterface<array<string, WebFile|string|null>, array<string, WebFile|string|null>>
 */
final class DirectlyUploadedWebFileTransformer implements DataTransformerInterface
{
    private FileManager $fileManager;
    private FileFactory $fileFactory;
    private bool $temporary;
    private string $fileField;
    private string $pathField;
    private string $fileSystemName;

    public function __construct(
        FileManager $fileManager,
        FileFactory $fileFactory,
        bool $temporary,
        string $fileField,
        string $pathField,
        string $fileSystemName,
    ) {
        $this->fileManager = $fileManager;
        $this->fileFactory = $fileFactory;
        $this->temporary = $temporary;
        $this->fileField = $fileField;
        $this->pathField = $pathField;
        $this->fileSystemName = $fileSystemName;
    }

    public function transform($value)
    {
        return $value;
    }

    public function reverseTransform($value)
    {
        if (false === is_array($value)) {
            return $value;
        }

        if (
            true === array_key_exists($this->pathField, $value)
            && null !== ($value[$this->pathField] ?: null)
            && true === is_string($value[$this->pathField])
        ) {
            if (true === $this->temporary) {
                $webFile = $this->fileFactory->createTemporary(
                    $this->fileSystemName,
                    $value[$this->pathField]
                );
            } else {
                $webFile = $this->fileFactory->createDirectlyUploaded(
                    $this->fileSystemName,
                    $value[$this->pathField]
                );
            }

            if (false === $this->fileManager->exists($webFile)) {
                throw new TransformationFailedException('File was not uploaded', 0, null, 'No file was uploaded.');
            }

            $value[$this->fileField] = $webFile;
        }

        return $value;
    }
}
