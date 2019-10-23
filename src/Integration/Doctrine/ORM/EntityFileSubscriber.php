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
use FSi\Component\Files\EventListener\EntityFileLoader;
use FSi\Component\Files\EventListener\EntityFileRemover;
use FSi\Component\Files\EventListener\EntityFileUpdater;
use function array_walk;

final class EntityFileSubscriber implements EventSubscriber
{
    /**
     * @var EntityFileRemover
     */
    private $entityFileRemover;

    /**
     * @var EntityFileUpdater
     */
    private $entityFileUpdater;

    /**
     * @var EntityFileLoader
     */
    private $entityFileLoader;

    public function __construct(
        EntityFileLoader $entityFileLoader,
        EntityFileUpdater $entityFileUpdater,
        EntityFileRemover $entityFileRemover
    ) {
        $this->entityFileLoader = $entityFileLoader;
        $this->entityFileUpdater = $entityFileUpdater;
        $this->entityFileRemover = $entityFileRemover;
    }

    public function getSubscribedEvents(): array
    {
        return [Events::postLoad, Events::prePersist, Events::preRemove, Events::preFlush, Events::postFlush];
    }

    public function postLoad(LifecycleEventArgs $event): void
    {
        $this->entityFileLoader->loadEntityFiles($event->getEntity());
    }

    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->entityFileUpdater->updateFiles($event->getEntity());
    }

    public function preRemove(LifecycleEventArgs $event): void
    {
        $this->entityFileRemover->clearEntityFiles($event->getEntity());
    }

    public function preFlush(PreFlushEventArgs $eventArgs): void
    {
        $identityMap = $eventArgs->getEntityManager()->getUnitOfWork()->getIdentityMap();

        array_walk($identityMap, function (array $entities): void {
            array_walk($entities, function (object $entity): void {
                $this->entityFileUpdater->updateFiles($entity);
            });
        });
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        $this->entityFileRemover->flush();
    }
}
