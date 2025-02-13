<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\DependencyInjection\CompilerPass;

use FSi\Component\Files\DirectUpload\Controller\LocalUploadController;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function interface_exists;

final class RemoveLocalUploadControllerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (true === interface_exists(ClockInterface::class)) {
            return;
        }

        $container->removeDefinition(LocalUploadController::class);
    }
}
