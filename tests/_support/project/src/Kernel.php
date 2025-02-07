<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\App;

use Aws\MockHandler;
use Aws\S3\S3Client;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use FSi\Component\Files\DirectUpload\DirectUploadTargetEncryptor;
use FSi\Component\Files\Integration\AmazonS3\UrlAdapter\S3PrivateUrlAdapter;
use FSi\Component\Files\Integration\FlySystem;
use FSi\Component\Files\Integration\Symfony\FilesBundle;
use FSi\Component\Files\Upload\FileFactory;
use FSi\Component\Files\Upload\PhpFilesHandler;
use FSi\Component\Files\UrlAdapter\BaseUrlAdapter;
use League\Flysystem\MountManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Oneup\FlysystemBundle\OneupFlysystemBundle;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\ArgumentValueResolver\PsrServerRequestResolver;
use Symfony\Bridge\PsrHttpMessage\EventListener\PsrResponseListener;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpKernel;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tests\FSi\App\Controller\IndexController;
use Tests\FSi\App\Controller\MultipleUploadController;
use Tests\FSi\App\Controller\NativeFilesController;
use Tests\FSi\App\Controller\SymfonyFilesController;
use Tests\FSi\App\Entity\ChildFileEntity;
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
                'path' => sprintf('%s/../var/data.sqlite', __DIR__),
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
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->loadFromExtension('oneup_flysystem', [
            'adapters' => [
                'local_adapter' => [
                    'local' => [
                        'location' => sprintf('%s/../public/files', __DIR__)
                    ],
                ],
                'other_local_adapter' => [
                    'local' => [
                        'location' => sprintf('%s/../public/other_files', __DIR__)
                    ],
                ],
                'private_adapter' => [
                    'local' => [
                        'location' => sprintf('%s/../var/private_files', __DIR__)
                    ],
                ],
                's3_adapter' => [
                    'awss3v3' => [
                        'client' => S3Client::class,
                        'bucket' => 'test',
                    ],
                ],
            ],
            'filesystems' => [
                'public' => [
                    'adapter' => 'local_adapter',
                    'mount' => 'public',
                ],
                'other_public' => [
                    'adapter' => 'other_local_adapter',
                    'mount' => 'other_public',
                ],
                'private' => [
                    'adapter' => 'private_adapter',
                    'mount' => 'private',
                ],
                'remote' => [
                    'adapter' => 's3_adapter',
                    'mount' => 'remote',
                ],
            ],
        ]);

        $container->loadFromExtension('fsi_files', [
            'default_entity_filesystem' => 'public',
            'direct_upload' => [
                'local_upload_path' => '/upload',
                'signature_expiration' => '+2 seconds',
            ],
            'url_adapters' => [
                'public' => 'fsi_files.url_adapter.public',
                'other_public' => 'fsi_files.url_adapter.other_public',
                'remote' => 'fsi_files.url_adapter.remote',
            ],
            'entities' => [
                ChildFileEntity::class => [
                    'fields' => [
                        [
                            'name' => 'file',
                            'prefix' => 'child_file'
                        ]
                    ]
                ],
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
                        ],
                        [
                            'name' => 'temporaryFile',
                            'filesystem' => 'public',
                            'pathField' => 'temporaryFileKey',
                            'prefix' => 'temporary-file'
                        ],
                        [
                            'name' => 'directFile',
                            'filesystem' => 'public',
                            'pathField' => 'directFileKey',
                            'prefix' => 'direct-file'
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
        $this->registerPublicControllerService($container, MultipleUploadController::class);

        $container->register(Psr18Client::class);
        $psr17Factory = $container->register(Psr17Factory::class);
        $container->setAlias(UriFactoryInterface::class, Psr17Factory::class);
        $container->setAlias(StreamFactoryInterface::class, Psr17Factory::class);
        $container->setAlias(RequestFactoryInterface::class, Psr17Factory::class);
        $container->setAlias(ServerRequestFactoryInterface::class, Psr17Factory::class);
        $container->setAlias(ResponseFactoryInterface::class, Psr17Factory::class);
        $container->setAlias(UploadedFileFactoryInterface::class, Psr17Factory::class);
        $container->setAlias(ClientInterface::class, Psr18Client::class);
        $container->setAlias(MountManager::class, 'oneup_flysystem.mount_manager');
        $container->getDefinition(DirectUploadTargetEncryptor::class)->setPublic(true);

        $container->register(PsrResponseListener::class)->setAutowired(true)->setAutoconfigured(true);
        $container->register(PsrHttpFactory::class)->setAutowired(true)->setAutoconfigured(true);
        $container->setAlias(HttpMessageFactoryInterface::class, PsrHttpFactory::class);
        $container->register(PsrServerRequestResolver::class)->setAutowired(true)->setAutoconfigured(true);
        $container->register(NativeClock::class);
        $container->setAlias(ClockInterface::class, NativeClock::class);
        $container->setAlias(FormFactoryInterface::class, 'form.factory')->setPublic(true);

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
        $container->register('fsi_files.url_adapter.remote', S3PrivateUrlAdapter::class)
            ->setArguments([
                '$s3Client' => new Reference(S3Client::class),
                '$s3Bucket' => 'test',
            ]);

        $this->registerS3ClientMock($container);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('index', '/')->controller(IndexController::class);
        $routes->add('native_files', '/native')->controller(NativeFilesController::class);
        $routes->add('symfony_files', '/symfony')->controller(SymfonyFilesController::class);
        $routes->add('multiple_symfony_files', '/multiple')->controller(MultipleUploadController::class);
        $routes->import('@FilesBundle/Resources/config/routing/direct_upload.yaml')->prefix('/upload');
        $routes->import('@FilesBundle/Resources/config/routing/local_upload.yaml')->prefix('/upload');
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

    private function registerS3ClientMock(ContainerBuilder $container): void
    {
        $container->register(S3Client::class)
            ->setArguments([
                '$args' => [
                    'version' => '2006-03-01',
                    'region' => 'eu-east-1',
                    'credentials' => [
                        'key' => 'FAKEKEY',
                        'secret' => 'FAKESECRET',
                    ],
                    'handler' => new Reference(MockHandler::class),
                ],
            ]);

        $container->register(MockHandler::class)->setPublic(true);
    }
}
