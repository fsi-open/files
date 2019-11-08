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
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('fsi_files');

        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();
        $root
            ->children()
                ->arrayNode('entities')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')->cannotBeEmpty()->end()
                            ->scalarNode('prefix')->cannotBeEmpty()->end()
                            ->scalarNode('filesystem')->cannotBeEmpty()->end()
                            ->arrayNode('fields')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('name')->cannotBeEmpty()->end()
                                        ->scalarNode('filesystem')->defaultNull()->end()
                                        ->scalarNode('pathField')->defaultNull()->end()
                                        ->scalarNode('prefix')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
