<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form\Transformer;

use FSi\Component\Files\WebFile;
use Symfony\Component\Form\DataTransformerInterface;

use function array_key_exists;
use function is_array;

/**
 * @implements DataTransformerInterface<WebFile|null, array<string, WebFile|string|null>>
 */
final class CompoundWebFileTransformer implements DataTransformerInterface
{
    private string $fileField;

    public function __construct(string $fileField)
    {
        $this->fileField = $fileField;
    }

    public function transform($value)
    {
        if (null === $value) {
            return [];
        }

        return [$this->fileField => $value];
    }

    public function reverseTransform($value)
    {
        if (false === is_array($value)) {
            return null;
        }

        if (
            true === array_key_exists($this->fileField, $value)
            && true === $value[$this->fileField] instanceof WebFile
        ) {
            return $value[$this->fileField];
        }

        return null;
    }
}
