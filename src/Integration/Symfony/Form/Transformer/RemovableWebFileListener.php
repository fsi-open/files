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
use function array_key_exists;
use function is_array;

final class RemovableWebFileListener
{
    public function __invoke(FormEvent $event): void
    {
        $data = $event->getData();
        $removableField = $event->getForm()->getConfig()->getOption('remove_field_name');
        if (false === is_array($data) || false === array_key_exists($removableField, $data)) {
            return;
        }

        if ('1' !== $data[$removableField]) {
            return;
        }

        $fileFieldName = $event->getForm()->getName();
        $event->getForm()->get($fileFieldName)->setData(null);

        unset($data[$fileFieldName]);
        $event->setData($data);
    }
}
