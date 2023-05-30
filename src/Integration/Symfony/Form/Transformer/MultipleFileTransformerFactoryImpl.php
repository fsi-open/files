<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form\Transformer;

final class MultipleFileTransformerFactoryImpl implements MultipleFileTransformerFactory
{
    public function create(FormFileTransformer $fileTransformer): MultipleFileTransformer
    {
        return new MultipleFileTransformerImpl($fileTransformer);
    }
}
