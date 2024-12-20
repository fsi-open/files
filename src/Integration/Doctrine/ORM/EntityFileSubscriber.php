<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Doctrine\ORM;

use ArrayAccess;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\Proxy;
use FSi\Component\Files\Entity\FileLoader;
use FSi\Component\Files\Entity\FileRemover;
use FSi\Component\Files\Entity\FileUpdater;

use function array_walk;
use function get_class;
use function method_exists;

final class EntityFileSubscriber implements EventSubscriber
{
    private FileRemover $fileRemover;
    private FileUpdater $fileUpdater;
    private FileLoader $fileLoader;

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

    /**
     * @param LifecycleEventArgs<ObjectManager> $event
     */
    public function postLoad(LifecycleEventArgs $event): void
    {
        $this->callIterativelyForObjectAndItsEmbbedables(
            [$this->fileLoader, 'loadEntityFiles'],
            $event->getObjectManager(),
            $event->getObject()
        );
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $event
     */
    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->callIterativelyForObjectAndItsEmbbedables(
            [$this->fileUpdater, 'updateFiles'],
            $event->getObjectManager(),
            $event->getObject()
        );
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $event
     */
    public function preRemove(LifecycleEventArgs $event): void
    {
        $this->callIterativelyForObjectAndItsEmbbedables(
            [$this->fileRemover, 'clearEntityFiles'],
            $event->getObjectManager(),
            $event->getObject()
        );
    }

    public function preFlush(PreFlushEventArgs $eventArgs): void
    {
        $manager = $eventArgs->getObjectManager();
        if (false === method_exists($manager, 'getUnitOfWork')) {
            return;
        }

        $identityMap = $manager->getUnitOfWork()->getIdentityMap();
        array_walk($identityMap, function (array $entities) use ($manager): void {
            array_walk($entities, function (?object $entity) use ($manager): void {
                if (null === $entity) {
                    return;
                }

                $this->callIterativelyForObjectAndItsEmbbedables(
                    [$this->fileUpdater, 'updateFiles'],
                    $manager,
                    $entity
                );
            });
        });
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        $this->fileUpdater->flush();
        $this->fileRemover->flush();
    }

    private function callIterativelyForObjectAndItsEmbbedables(
        callable $callable,
        ObjectManager $manager,
        object $object
    ): void {
        if (true === $object instanceof Proxy && false === $object->__isInitialized()) {
            return;
        }

        $callable($object);

        /** @var ClassMetadata<object> $metadata */
        $metadata = $manager->getClassMetadata(get_class($object));
        if (false === $metadata instanceof ORMClassMetadata) {
            return;
        }

        array_walk(
            $metadata->embeddedClasses,
            function (
                array|ArrayAccess $configuration,
                string $property,
                callable $callable
            ) use (
                $object,
                $manager,
                $metadata
            ): void {
                if (null !== $configuration['declaredField'] || null !== $configuration['originalField']) {
                    return;
                }

                $embeddable = $metadata->getFieldValue($object, $property);
                if (null === $embeddable) {
                    return;
                }

                $this->callIterativelyForObjectAndItsEmbbedables($callable, $manager, $embeddable);
            },
            $callable
        );
    }
}
