<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\DependencyInjection\CompilerPass;

use FSi\Component\Files\Integration\Symfony\DependencyInjection\Configuration;
use FSi\Component\Files\Integration\Symfony\Form\WebFileType;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TemporaryFilesystemPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $processor = new Processor();
        $configuration = $processor->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig('fsi_files')
        );

        if (null !== $configuration['direct_upload']['temporary_filesystem']) {
            $webFileTypeDefinition = $container->getDefinition(WebFileType::class);
            $webFileTypeDefinition->setArgument(
                '$temporaryFileSystemName',
                $configuration['direct_upload']['temporary_filesystem']
            );
        }

        if (null !== $configuration['direct_upload']['temporary_prefix']) {
            $webFileTypeDefinition = $container->getDefinition(WebFileType::class);
            $webFileTypeDefinition->setArgument(
                '$temporaryFileSystemPrefix',
                $configuration['direct_upload']['temporary_prefix']
            );
        }
    }
}
