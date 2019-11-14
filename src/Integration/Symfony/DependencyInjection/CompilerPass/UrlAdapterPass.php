<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\DependencyInjection\CompilerPass;

use FSi\Component\Files\FileUrlResolver;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function sprintf;

final class UrlAdapterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $resolver = $container->getDefinition(FileUrlResolver::class);
        if (null === $resolver) {
            throw new RuntimeException(sprintf('%s" is not registered!', FileUrlResolver::class));
        }

        $adaptersServices = array_reduce(
            array_keys($container->findTaggedServiceIds('fsi_files.url_adapter')),
            function (array $accumulator, string $id) use ($container): array {
                $accumulator[$id] = $container->getDefinition($id);
                return $accumulator;
            },
            []
        );

        $resolver->replaceArgument(
            '$adapters',
            array_reduce(
                $container->getExtensionConfig('fsi_files')[0]['adapters'],
                function (array $accumulator, array $configuration) use ($adaptersServices): array {
                    $filesystem = $configuration['filesystem'];
                    if (true === array_key_exists($filesystem, $accumulator)) {
                        throw new RuntimeException("Duplicate entry for filesystem \"{$filesystem}\".");
                    }

                    $service = $configuration['service'];
                    if (false === array_key_exists($service, $adaptersServices)) {
                        throw new RuntimeException(
                            "Service \"{$service}\" does not exist for filesystem \"{$filesystem}\"."
                        );
                    }

                    $adapter = $adaptersServices[$service];
                    if (true === in_array($adapter, $accumulator, true)) {
                        throw new RuntimeException("Service \"{$service}\" is used more than one time.");
                    }

                    $accumulator[$filesystem] = $adapter;
                    return $accumulator;
                },
                []
            )
        );
    }
}
