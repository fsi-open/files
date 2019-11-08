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
use FSi\Component\Files\Upload\PhpFilesHandler;
use FSi\Tests\App\Controller\IndexController;
use FSi\Tests\App\Controller\NativeFilesController;
use FSi\Tests\App\Controller\SymfonyFilesController;
use FSi\Tests\App\Entity\FileEntity;
use Oneup\FlysystemBundle\OneupFlysystemBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;
use function sprintf;

final class Kernel extends HttpKernel\Kernel implements CompilerPassInterface
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

    public function process(ContainerBuilder $container)
    {
        $container->getDefinition(PhpFilesHandler::class)->setPublic(true);
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
                ],
                'temporary_other' => [
                    'adapter' => 'memory_adapter',
                    'mount' => 'temporary'
                ]
            ]
        ]);

        $container->loadFromExtension('fsi_files', [
            'entities' => [
                [
                    'class' => FileEntity::class,
                    'prefix' => 'file_entity',
                    'filesystem' => 'temporary',
                    'fields' => [
                        ['name' => 'file'],
                        [
                            'name' => 'anotherFile',
                            'filesystem' => 'temporary_other',
                            'pathField' => 'anotherFileKey',
                            'prefix' => 'anotherFile'
                        ]
                    ]
                ]
            ]
        ]);

        $this->registerPublicControllerService($container, IndexController::class);
        $this->registerPublicControllerService($container, NativeFilesController::class);
        $this->registerPublicControllerService($container, SymfonyFilesController::class);
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $routes->add('/', IndexController::class, 'index');
        $routes->add('/native', NativeFilesController::class, 'native_files');
        $routes->add('/symfony', SymfonyFilesController::class, 'symfony_files');
    }

    private function registerPublicControllerService(ContainerBuilder $container, string $class): void
    {
        $definition = $container->register($class);
        $definition->setAutowired(true);
        $definition->setPublic(true);
    }
}
