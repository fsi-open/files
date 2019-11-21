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
use Symfony\Component\Form\FormEvent;

final class NullToExistingWebFileTransformer implements FormFileTransformer
{
    public function __invoke(FormEvent $event): void
    {
        if (null !== $event->getData()) {
            return;
        }

        $currentWebFile = $event->getForm()->getData();
        if (false === $currentWebFile instanceof WebFile) {
            return;
        }

        // This prevents existing files being overwritten by null in case if
        // there was no new file provided. Files should be removed explicitly.
        $event->setData($currentWebFile);
    }
}
