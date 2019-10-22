<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use FSi\Component\Files\FlySystem\FilePropertyConfiguration;
use FSi\Component\Files\FlySystem\FilePropertyConfigurationResolver;
use function array_walk;

class EntityFileSubscriber implements EventSubscriber
{
    /**
     * @var FilePropertyConfigurationResolver
     */
    private $configurationResolver;

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
        FilePropertyConfigurationResolver $configurationResolver,
        EntityFileLoader $entityFileLoader,
        EntityFileUpdater $entityFileUpdater,
        EntityFileRemover $entityFileRemover
    ) {
        $this->configurationResolver = $configurationResolver;
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
        $entity = $event->getEntity();
        $configurations = $this->configurationResolver->resolveEntity($entity);

        $fileLoader = function (FilePropertyConfiguration $configuration) use ($entity): void {
            $configuration->getFilePropertyReflection()->setValue(
                $entity,
                $this->entityFileLoader->fromEntity($configuration, $entity)
            );
        };

        array_walk($configurations, $fileLoader);
    }

    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->entityFileUpdater->updateFiles($event->getEntity());
    }

    public function preRemove(LifecycleEventArgs $event): void
    {
        $entity = $event->getEntity();
        $configurations = $this->configurationResolver->resolveEntity($entity);

        $fileRemover = function (FilePropertyConfiguration $configuration) use ($entity): void {
            $file = $this->entityFileLoader->fromEntity($configuration, $entity);
            if (null === $file) {
                return;
            }

            $this->entityFileRemover->add($file);
        };

        array_walk($configurations, $fileRemover);
    }

    public function preFlush(PreFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getEntityManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                $this->entityFileUpdater->updateFiles($entity);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        $this->entityFileRemover->flush();
    }
}
