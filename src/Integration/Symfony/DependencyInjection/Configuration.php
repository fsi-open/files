<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('fsi_files');

        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();

        /** @var NodeBuilder $rootChildren */
        $rootChildren = $root->children();
        $rootChildren->scalarNode('default_entity_filesystem')->defaultNull();
        $directUploadNode = $rootChildren->arrayNode('direct_upload')->addDefaultsIfNotSet()->children();
        $directUploadNode->scalarNode('temporary_filesystem')->defaultNull();
        $directUploadNode->scalarNode('temporary_prefix')->defaultNull();
        $directUploadNode->scalarNode('signature_expiration')->defaultValue('+1 hour')->end();
        $directUploadNode->scalarNode('local_upload_path')->defaultNull()->end();
        $directUploadNode->scalarNode('local_upload_signature_algo')->defaultValue('sha512')->end();
        $directUploadNode->end();

        /** @var ArrayNodeDefinition $adaptersNode */
        $adaptersNode = $rootChildren->arrayNode('url_adapters')->beforeNormalization()->castToArray()->end();
        $adaptersNode->useAttributeAsKey('filesystem')->prototype('scalar')->end();
        $adaptersNode->end();

        /** @var NodeBuilder $entitiesChildren */
        $entitiesChildren = $rootChildren->arrayNode('entities')
            ->useAttributeAsKey('class')
            ->arrayPrototype()
            ->children()
        ;
        $entitiesChildren->scalarNode('prefix')->defaultNull()->end();
        $entitiesChildren->scalarNode('filesystem')->defaultNull()->end();

        /** @var ArrayNodeDefinition $fieldsChildrenNode */
        $fieldsChildrenNode = $entitiesChildren->arrayNode('fields')->beforeNormalization()->castToArray()->end();
        /** @var ArrayNodeDefinition $fieldsChildrenPrototype */
        $fieldsChildrenPrototype = $fieldsChildrenNode->arrayPrototype()
            ->beforeNormalization()
                ->ifTrue(
                    static fn($argument): bool => is_string($argument)
                )
                ->then(
                    static fn(string $argument): array
                        => [
                            'name' => $argument,
                            'filesystem' => null,
                            'pathField' => null,
                            'prefix' => null
                        ]
                )
                ->end()
        ;

        /** @var NodeBuilder $fieldsChildren */
        $fieldsChildren = $fieldsChildrenPrototype->children();
        $fieldsChildren->scalarNode('name')->defaultNull()->end();
        $fieldsChildren->scalarNode('filesystem')->defaultNull()->end();
        $fieldsChildren->scalarNode('pathField')->defaultNull()->end();
        $fieldsChildren->scalarNode('prefix')->defaultNull()->end();

        $fieldsChildren->end();
        $entitiesChildren->end();
        $rootChildren->end();
        $root->end();

        return $treeBuilder;
    }
}
