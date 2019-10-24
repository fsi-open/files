<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Doctrine\ORM;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use FSi\Component\Files\Entity\FileLoader;
use FSi\Component\Files\Entity\FileRemover;
use FSi\Component\Files\Entity\FileUpdater;
use function array_walk;

final class EntityFileSubscriber implements EventSubscriber
{
    /**
     * @var FileRemover
     */
    private $fileRemover;

    /**
     * @var FileUpdater
     */
    private $fileUpdater;

    /**
     * @var FileLoader
     */
    private $fileLoader;

    public function __construct(
        FileLoader $fileLoader,
        FileUpdater $fileUpdater,
        FileRemover $fileRemover
    ) {
        $this->fileLoader = $fileLoader;
        $this->fileUpdater = $fileUpdater;
        $this->fileRemover = $fileRemover;
    }

    public function getSubscribedEvents(): array
    {
        return [Events::postLoad, Events::prePersist, Events::preRemove, Events::preFlush, Events::postFlush];
    }

    public function postLoad(LifecycleEventArgs $event): void
    {
        $this->fileLoader->loadEntityFiles($event->getEntity());
    }

    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->fileUpdater->updateFiles($event->getEntity());
    }

    public function preRemove(LifecycleEventArgs $event): void
    {
        $this->fileRemover->clearEntityFiles($event->getEntity());
    }

    public function preFlush(PreFlushEventArgs $eventArgs): void
    {
        $identityMap = $eventArgs->getEntityManager()->getUnitOfWork()->getIdentityMap();

        array_walk($identityMap, function (array $entities): void {
            array_walk($entities, function (object $entity): void {
                $this->fileUpdater->updateFiles($entity);
            });
        });
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        $this->fileRemover->flush();
    }
}
