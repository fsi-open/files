<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Component\Files\Integration\Symfony\DependencyInjection;

use Codeception\Test\Unit;
use FSi\Component\Files\Integration\Symfony\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends Unit
{
    private Processor $processor;

    public function testConfiguration(): void
    {
        $parsedConfiguration = $this->processor->processConfiguration(
            new Configuration(),
            [
                [
                    'url_adapters' => [
                        'temporary' => 'adapter_service_id'
                    ],
                    'entities' => [
                        'stdClass' => [
                            'prefix' => 'std_class',
                            'filesystem' => 'temporary',
                            'fields' => [
                                [
                                    'name' => 'file',
                                    'filesystem' => 'memory',
                                    'pathField' => 'fileKey',
                                    'prefix' => 'someprefix'
                                ],
                                'anotherFile'
                            ]
                        ]
                    ]
                ]
            ]
        );

        $this->assertSame(
            [
                'url_adapters' => [
                    'temporary' => 'adapter_service_id'
                ],
                'entities' => [
                    'stdClass' => [
                        'prefix' => 'std_class',
                        'filesystem' => 'temporary',
                        'fields' => [
                            [
                                'name' => 'file',
                                'filesystem' => 'memory',
                                'pathField' => 'fileKey',
                                'prefix' => 'someprefix'
                            ],
                            [
                                'name' => 'anotherFile',
                                'filesystem' => null,
                                'pathField' => null,
                                'prefix' => null
                            ]
                        ]
                    ]
                ]
            ],
            $parsedConfiguration
        );
    }

    protected function setUp(): void
    {
        $this->processor = new Processor();
    }
}
