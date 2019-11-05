<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\App;

use FSi\Component\Files\Integration\Symfony\FilesBundle;
use FSi\Tests\App\Controller\IndexController;
use Oneup\FlysystemBundle\OneupFlysystemBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;
use function sprintf;

final class Kernel extends HttpKernel\Kernel
{
    use MicroKernelTrait;

    public function registerBundles()
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new OneupFlysystemBundle(),
            new FilesBundle()
        ];
    }

    public function getCacheDir()
    {
        return sprintf('%s/../var/cache/%s', __DIR__, $this->getEnvironment());
    }

    public function getLogDir()
    {
        return sprintf('%s/../var/log', __DIR__);
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader)
    {
        $container->loadFromExtension('framework', [
            'secret' => 'fsi_component_files_secret'
        ]);

        $container->loadFromExtension('twig', [
            'paths' => [sprintf('%s/../templates', __DIR__)]
        ]);

        $container->loadFromExtension('oneup_flysystem', [
            'adapters' => [
                'memory_adapter' => ['memory' => null]
            ],
            'filesystems' => [
                'temporary' => [
                    'adapter' => 'memory_adapter',
                    'mount' => 'temporary'
                ]
            ]
        ]);

        $controllerDefinition = $container->register(IndexController::class);
        $controllerDefinition->setAutowired(true);
        $controllerDefinition->setPublic(true);
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $routes->add('/', IndexController::class, 'index');
    }
}
