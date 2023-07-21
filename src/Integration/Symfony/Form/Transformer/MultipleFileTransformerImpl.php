<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form\Transformer;

use Symfony\Component\Form\Event\PreSubmitEvent;

final class MultipleFileTransformerImpl implements MultipleFileTransformer
{
    private FormFileTransformer $fileTransformer;

    public function __construct(FormFileTransformer $fileTransformer)
    {
        $this->fileTransformer = $fileTransformer;
    }

    public function __invoke(PreSubmitEvent $event): void
    {
        $data = (null === $event->getData()) ? [null] : ((array) $event->getData());
        $keys = array_keys($data);
        array_walk(
            $keys,
            function ($key) use (&$data, $event): void {
                $subEvent = new PreSubmitEvent($event->getForm(), $data[$key]);
                ($this->fileTransformer)($subEvent);
                $data[$key] = $subEvent->getData();
            }
        );
        $event->setData($data);
    }
}
