<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form\Transformer;

use Symfony\Component\Form\FormEvent;

interface FormFileTransformer
{
    public function __invoke(FormEvent $event): void;
}
