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

namespace ProophTest\HttplugEventStore;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use PHPUnit\Framework\TestCase;
use Prooph\Common\Messaging\MessageConverter;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\StreamNotFound;
use Prooph\EventStore\Httplug\HttplugEventStore;
use Prooph\EventStore\StreamName;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class HttplugEventStoreTest extends TestCase
{
    /**
     * @test
     */
    public function it_throws_exception_when_cannot_json_encode_metadata_for_update(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata could not be json encoded');

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $this->prophesize(HttpClient::class)->reveal(),
            $this->prophesize(UriInterface::class)->reveal(),
            $this->prophesize(RequestFactory::class)->reveal()
        );

        $eventStore->updateStreamMetadata(new StreamName('test'), ["\xB1\x31"]);
    }

    /**
     * @test
     */
    public function it_throws_stream_not_found_when_trying_to_update_unknown_stream_metadata(): void
    {
        $this->expectException(StreamNotFound::class);

        $finalUrl = $this->prophesize(UriInterface::class);
        $finalUrl = $finalUrl->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('streammetadata/unknown')->willReturn($finalUrl);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUrl,
                [
                    'Content-Type' => 'application/json',
                ],
                json_encode(['some' => 'value'])
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(404)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
            $uri->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->updateStreamMetadata(new StreamName('unknown'), ['some' => 'value']);
    }

    /**
     * @test
     */
    public function it_updates_stream_metadata(): void
    {
        $finalUrl = $this->prophesize(UriInterface::class);
        $finalUrl = $finalUrl->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('streammetadata/somename')->willReturn($finalUrl);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUrl,
                [
                    'Content-Type' => 'application/json',
                ],
                json_encode(['some' => 'value'])
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(204)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
            $uri->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->updateStreamMetadata(new StreamName('somename'), ['some' => 'value']);
    }
}
