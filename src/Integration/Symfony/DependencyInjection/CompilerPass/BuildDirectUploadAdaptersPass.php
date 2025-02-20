<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\DependencyInjection\CompilerPass;

use Assert\Assertion;
use FSi\Component\Files\DirectUpload\AdapterRegistry;
use FSi\Component\Files\DirectUpload\Controller\LocalUploadSigner;
use FSi\Component\Files\Integration\FlySystem\DirectUpload\LocalAdapter;
use FSi\Component\Files\Integration\FlySystem\DirectUpload\S3Adapter;
use FSi\Component\Files\Integration\Symfony\DependencyInjection\Configuration as FilesConfiguration;
use FSi\Component\Files\Integration\Symfony\DependencyInjection\FilesExtension;
use Oneup\FlysystemBundle\DependencyInjection\Configuration as FlySystemConfiguration;
use Oneup\FlysystemBundle\DependencyInjection\OneupFlysystemExtension;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function class_exists;
use function interface_exists;

final class BuildDirectUploadAdaptersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $processor = new Processor();

        $flySystemConfigs = $container->getExtensionConfig('oneup_flysystem');
        $flySystemExtension = $container->getExtension('oneup_flysystem');
        Assertion::isInstanceOf($flySystemExtension, OneupFlysystemExtension::class);
        $flySystemConfiguration = $flySystemExtension->getConfiguration($flySystemConfigs, $container);
        Assertion::isInstanceOf($flySystemConfiguration, FlySystemConfiguration::class);
        $flySystemConfig = $processor->processConfiguration($flySystemConfiguration, $flySystemConfigs);

        $filesConfigs = $container->getExtensionConfig('fsi_files');
        $filesExtension = $container->getExtension('fsi_files');
        Assertion::isInstanceOf($filesExtension, FilesExtension::class);
        $filesConfiguration = $filesExtension->getConfiguration($filesConfigs, $container);
        Assertion::isInstanceOf($filesConfiguration, FilesConfiguration::class);
        $filesConfig = $processor->processConfiguration($filesConfiguration, $filesConfigs);

        $adapters = [];
        foreach ($flySystemConfig['filesystems'] as $filesystemName => $filesystem) {
            $adapterId = $filesystem['adapter'];
            $adapterConfig = $flySystemConfig['adapters'][$adapterId];
            if (true === array_key_exists('awss3v3', $adapterConfig)) {
                $adapters[$filesystem['mount'] ?? $filesystemName] = new Definition(S3Adapter::class, [
                    new Reference($adapterConfig['awss3v3']['client']),
                    $adapterConfig['awss3v3']['bucket'],
                    $adapterConfig['awss3v3']['path_prefix'] ?? '',
                    $adapterConfig['awss3v3']['options'] ?? [],
                    $filesystem['visibility'],
                    $filesConfig['direct_upload']['signature_expiration'],
                ]);
            } else {
                $localUploadPath = $filesConfig['direct_upload']['local_upload_path'];
                if (false === is_string($localUploadPath)) {
                    continue;
                }
                if (false === interface_exists(ClockInterface::class)) {
                    throw new RuntimeException(
                        'To use LocalAdapter you need to install "psr/clock" package and configure '
                            . 'Psr\\Clock\\ClockInterface service with some implementation'
                    );
                }
                $adapters[$filesystem['mount'] ?? $filesystemName] = (new Definition(LocalAdapter::class, [
                    '$localUploadPath' => $localUploadPath,
                    '$signatureExpiration' => $filesConfig['direct_upload']['signature_expiration'],
                ]))->setAutowired(true);
            }
        }

        $adapterRegistry = $container->getDefinition(AdapterRegistry::class);
        $adapterRegistry->setArgument('$adapters', $adapters);

        $resolverDefinition = $container->getDefinition(LocalUploadSigner::class);
        $resolverDefinition->setArgument(
            '$algorithm',
            $filesConfig['direct_upload']['local_upload_signature_algo']
        );
    }
}
