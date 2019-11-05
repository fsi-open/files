<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use function sprintf;

final class FilesBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $this->loadExternalBundlesServices($container);
    }

    private function loadExternalBundlesServices(ContainerBuilder $container): void
    {
        if (false === $container->hasExtension('oneup_flysystem')) {
            return;
        }

        $loader = new XmlFileLoader(
            $container,
            new FileLocator(sprintf('%s/Resources/config/services', __DIR__))
        );

        $loader->load('flysystem.xml');
    }
}