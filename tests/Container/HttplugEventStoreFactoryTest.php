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

namespace ProophTest\HttplugEventStore\Container;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use PHPUnit\Framework\TestCase;
use Prooph\Common\Messaging\FQCNMessageFactory;
use Prooph\Common\Messaging\NoOpMessageConverter;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Httplug\Container\HttplugEventStoreFactory;
use Prooph\EventStore\Httplug\HttplugEventStore;
use Psr\Container\ContainerInterface;

class HttplugEventStoreFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_httplug_event_store(): void
    {
        $config = [
            'prooph' => [
                'event_store' => [
                    'default' => [
                        'http_client' => 'client',
                        'request_factory' => 'requestFactory',
                    ],
                ],
            ],
        ];

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn($config)->shouldBeCalled();
        $container->get(FQCNMessageFactory::class)->willReturn(new FQCNMessageFactory())->shouldBeCalled();
        $container->get(NoOpMessageConverter::class)->willReturn(new NoOpMessageConverter())->shouldBeCalled();
        $container->get('client')->willReturn($this->prophesize(HttpClient::class)->reveal())->shouldBeCalled();
        $container->get('requestFactory')->willReturn($this->prophesize(RequestFactory::class)->reveal())->shouldBeCalled();

        $factory = new HttplugEventStoreFactory();
        $eventStore = $factory($container->reveal());

        $this->assertInstanceOf(HttplugEventStore::class, $eventStore);
    }

    /**
     * @test
     */
    public function it_creates_httplug_event_store_using_callstatic(): void
    {
        $config = [
            'prooph' => [
                'event_store' => [
                    'default' => [
                        'http_client' => 'client',
                        'request_factory' => 'requestFactory',
                    ],
                ],
            ],
        ];

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn($config)->shouldBeCalled();
        $container->get(FQCNMessageFactory::class)->willReturn(new FQCNMessageFactory())->shouldBeCalled();
        $container->get(NoOpMessageConverter::class)->willReturn(new NoOpMessageConverter())->shouldBeCalled();
        $container->get('client')->willReturn($this->prophesize(HttpClient::class)->reveal())->shouldBeCalled();
        $container->get('requestFactory')->willReturn($this->prophesize(RequestFactory::class)->reveal())->shouldBeCalled();

        $name = 'default';

        $eventStore = HttplugEventStoreFactory::$name($container->reveal());

        $this->assertInstanceOf(HttplugEventStore::class, $eventStore);
    }

    /**
     * @test
     */
    public function it_throws_invalid_argument_exception_when_invalid_container_given(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $name = 'default';

        HttplugEventStoreFactory::$name('invalid');
    }
}
