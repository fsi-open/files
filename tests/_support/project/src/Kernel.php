<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use FSi\Component\Files\Integration\FlySystem;
use FSi\Component\Files\Integration\Symfony\FilesBundle;
use FSi\Component\Files\Upload\FileFactory;
use FSi\Component\Files\Upload\PhpFilesHandler;
use FSi\Component\Files\UrlAdapter\BaseUrlAdapter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Oneup\FlysystemBundle\OneupFlysystemBundle;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpKernel;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Tests\FSi\App\Controller\IndexController;
use Tests\FSi\App\Controller\NativeFilesController;
use Tests\FSi\App\Controller\SymfonyFilesController;
use Tests\FSi\App\Entity\EmbeddedFile;
use Tests\FSi\App\Entity\FileEntity;
use Tests\FSi\App\Entity\TwiceEmbeddedFile;
use Tests\FSi\App\Form\FormTestType;

use function sprintf;

final class Kernel extends HttpKernel\Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    /**
     * @return array<Bundle>
     */
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new DoctrineBundle(),
            new OneupFlysystemBundle(),
            new FilesBundle()
        ];
    }

    public function getCacheDir(): string
    {
        return sprintf('%s/../var/cache/%s', __DIR__, $this->getEnvironment());
    }

    public function getLogDir(): string
    {
        return sprintf('%s/../var/log', __DIR__);
    }

    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition(PhpFilesHandler::class)->setPublic(true);
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
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
                        'prefix' => 'Tests\FSi\App\Entity',
                        'is_bundle' => false
                    ]
                ]
            ]
        ]);

        $container->loadFromExtension('oneup_flysystem', [
            'adapters' => [
                'local_adapter' => [
                    'local' => [
                        'location' => sprintf('%s/../public/files', __DIR__)
                    ]
                ],
                'other_local_adapter' => [
                    'local' => [
                        'location' => sprintf('%s/../public/other_files', __DIR__)
                    ]
                ],
                'private_adapter' => [
                    'local' => [
                        'location' => sprintf('%s/../var/private_files', __DIR__)
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
                ],
                'private' => [
                    'adapter' => 'private_adapter',
                    'mount' => 'private'
                ]
            ]
        ]);

        $container->loadFromExtension('fsi_files', [
            'default_entity_filesystem' => 'public',
            'url_adapters' => [
                'public' => 'fsi_files.url_adapter.public',
                'other_public' => 'fsi_files.url_adapter.other_public'
            ],
            'entities' => [
                FileEntity::class => [
                    'fields' => [
                        [
                            'name' => 'file',
                            'prefix' => 'file'
                        ],
                        [
                            'name' => 'anotherFile',
                            'filesystem' => 'other_public',
                            'pathField' => 'anotherFileKey',
                            'prefix' => 'anotherFile'
                        ],
                        [
                            'name' => 'privateFile',
                            'filesystem' => 'private',
                            'pathField' => 'privateFileKey',
                            'prefix' => 'private-file'
                        ]
                    ]
                ],
                EmbeddedFile::class => [
                    'prefix' => 'embeddable',
                    'filesystem' => 'public',
                    'fields' => ['file']
                ],
                TwiceEmbeddedFile::class => [
                    'prefix' => 'embeddable',
                    'filesystem' => 'public',
                    'fields' => ['file']
                ]
            ]
        ]);

        $this->registerPublicControllerService($container, IndexController::class);
        $this->registerPublicControllerService($container, NativeFilesController::class);
        $this->registerPublicControllerService($container, SymfonyFilesController::class);

        $container->register(Psr18Client::class);
        $psr17Factory = $container->register(Psr17Factory::class);
        $container->setAlias(UriFactoryInterface::class, Psr17Factory::class);
        $container->setAlias(StreamFactoryInterface::class, Psr17Factory::class);
        $container->setAlias(RequestFactoryInterface::class, Psr17Factory::class);
        $container->setAlias(ClientInterface::class, Psr18Client::class);

        $container->register(FormTestType::class)
            ->setAutoconfigured(true)
            ->setArgument('$uriFactory', $psr17Factory)
        ;

        $container->register('test.' . FileFactory::class, FlySystem\Upload\FileFactory::class)
            ->setPublic(true)
            ->setAutowired(true)
        ;

        $this->registerBaseUrlAdapterService(
            $container,
            'fsi_files.url_adapter.public',
            $psr17Factory,
            '/files/'
        );
        $this->registerBaseUrlAdapterService(
            $container,
            'fsi_files.url_adapter.other_public',
            $psr17Factory,
            '/other_files/'
        );
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
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
    }
}
