<?php
/**
 * This file is part of the prooph/httplug-event-store.
 * (c) 2017-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2017-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\HttplugEventStore\Container\Projection;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Httplug\Container\Projection\HttplugProjectionManagerFactory;
use Prooph\EventStore\Httplug\Projection\HttplugProjectionManager;
use Psr\Container\ContainerInterface;

class HttplugProjectionManagerFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_httplug_projection_manager(): void
    {
        $config = [
            'prooph' => [
                'projection_manager' => [
                    'default' => [
                        'http_client' => 'client',
                        'request_factory' => 'requestFactory',
                    ],
                ],
            ],
        ];

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn($config)->shouldBeCalled();
        $container->get('client')->willReturn($this->prophesize(HttpClient::class)->reveal())->shouldBeCalled();
        $container->get('requestFactory')->willReturn($this->prophesize(RequestFactory::class)->reveal())->shouldBeCalled();

        $factory = new HttplugProjectionManagerFactory();
        $eventStore = $factory($container->reveal());

        $this->assertInstanceOf(HttplugProjectionManager::class, $eventStore);
    }

    /**
     * @test
     */
    public function it_creates_httplug_projection_manager_using_callstatic(): void
    {
        $config = [
            'prooph' => [
                'projection_manager' => [
                    'default' => [
                        'http_client' => 'client',
                        'request_factory' => 'requestFactory',
                    ],
                ],
            ],
        ];

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn($config)->shouldBeCalled();
        $container->get('client')->willReturn($this->prophesize(HttpClient::class)->reveal())->shouldBeCalled();
        $container->get('requestFactory')->willReturn($this->prophesize(RequestFactory::class)->reveal())->shouldBeCalled();

        $name = 'default';

        $projectionManager = HttplugProjectionManagerFactory::$name($container->reveal());

        $this->assertInstanceOf(HttplugProjectionManager::class, $projectionManager);
    }

    /**
     * @test
     */
    public function it_throws_invalid_argument_exception_when_invalid_container_given(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $name = 'default';

        HttplugProjectionManagerFactory::$name('invalid');
    }
}
