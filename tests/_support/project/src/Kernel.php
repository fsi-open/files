<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Tests\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use FSi\Component\Files\Integration\Symfony\FilesBundle;
use FSi\Component\Files\Upload\PhpFilesHandler;
use FSi\Component\Files\UrlAdapter\BaseUrlAdapter;
use FSi\Tests\App\Controller\IndexController;
use FSi\Tests\App\Controller\NativeFilesController;
use FSi\Tests\App\Controller\SymfonyFilesController;
use FSi\Tests\App\Entity\FileEntity;
use FSi\Tests\App\Http\UriFactory;
use Oneup\FlysystemBundle\OneupFlysystemBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
            new DoctrineBundle(),
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

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'user' => 'admin',
                'charset' => 'UTF8',
                'path' => sprintf('%s/../var/data.sqlite', __DIR__)
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                'auto_mapping' => true,
                'mappings' => [
                    'shared_kernel' => [
                        'type' => 'xml',
                        'dir' => sprintf('%s/Resources/config/doctrine', __DIR__),
                        'alias' => 'FSi',
                        'prefix' => 'FSi\Tests\App\Entity',
                        'is_bundle' => false
                    ]
                ]
            ]
        ]);

        $container->loadFromExtension('oneup_flysystem', [
            'adapters' => [
                'local_adapter' => [
                    'local' => [
                        'directory' => sprintf('%s/../public/files', __DIR__)
                    ]
                ],
                'other_local_adapter' => [
                    'local' => [
                        'directory' => sprintf('%s/../public/other_files', __DIR__)
                    ]
                ]
            ],
            'filesystems' => [
                'public' => [
                    'adapter' => 'local_adapter',
                    'mount' => 'public'
                ],
                'other_public' => [
                    'adapter' => 'other_local_adapter',
                    'mount' => 'other_public'
                ]
            ]
        ]);

        $container->loadFromExtension('fsi_files', [
            'adapters' => [
                ['filesystem' => 'public', 'service' => 'fsi_files.url_adapter.public'],
                ['filesystem' => 'other_public', 'service' => 'fsi_files.url_adapter.other_public']
            ],
            'entities' => [
                [
                    'class' => FileEntity::class,
                    'prefix' => 'file_entity',
                    'filesystem' => 'public',
                    'fields' => [
                        ['name' => 'file'],
                        [
                            'name' => 'anotherFile',
                            'filesystem' => 'other_public',
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
        $uriFactory = $container->register(UriFactory::class);
        $this->registerBaseUrlAdapterService($container, 'fsi_files.url_adapter.public', $uriFactory, '/files/');
        $this->registerBaseUrlAdapterService(
            $container,
            'fsi_files.url_adapter.other_public',
            $uriFactory,
            '/other_files/'
        );
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

    private function registerBaseUrlAdapterService(
        ContainerBuilder $container,
        string $id,
        Definition $uriFactory,
        string $publicDirectory
    ): void {
        $definition = $container->register($id);
        $definition->setClass(BaseUrlAdapter::class);
        $definition->setArgument('$uriFactory', $uriFactory);
        $definition->setArgument('$baseUrl', $publicDirectory);
        $definition->addTag('fsi_files.url_adapter');
    }
}
