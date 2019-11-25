<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form\Transformer;

use FSi\Component\Files\Integration\Symfony\Form\WebFileType;
use Symfony\Component\Form\FormEvent;
use function array_key_exists;
use function is_array;

final class RemovableWebFileListener
{
    public function __invoke(FormEvent $event): void
    {
        $data = $event->getData();
        if (false === is_array($data) || false === array_key_exists(WebFileType::REMOVE_FIELD, $data)) {
            return;
        }

        if ('1' !== $data[WebFileType::REMOVE_FIELD]) {
            return;
        }

        $event->getForm()->get(WebFileType::FILE_FIELD)->setData(null);
        unset($data[WebFileType::FILE_FIELD]);
        $event->setData($data);
    }
}
