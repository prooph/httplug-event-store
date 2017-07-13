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
use Prooph\Common\Messaging\NoOpMessageConverter;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Exception\StreamNotFound;
use Prooph\EventStore\Httplug\Exception\NotAllowed;
use Prooph\EventStore\Httplug\HttplugEventStore;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use ProophTest\EventStore\Mock\TestDomainEvent;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class HttplugEventStoreTest extends TestCase
{
    //<editor-fold desc="updateStreamMetadata">

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

    /**
     * @test
     */
    public function it_throws_exception_when_forbidden_to_update_metadata(): void
    {
        $this->expectException(NotAllowed::class);

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
        $response->getStatusCode()->willReturn(405)->shouldBeCalled();

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
    public function it_throws_exception_on_unknown_error_when_updating_metadata(): void
    {
        $this->expectException(RuntimeException::class);

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
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

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

    //</editor-fold>

    //<editor-fold desc="create">

    /**
     * @test
     */
    public function it_creates_stream(): void
    {
        $messageConverter = new NoOpMessageConverter();

        $message = TestDomainEvent::with(['foo' => 'bar'], 1);
        $messageData = $messageConverter->convertToArray($message);
        $messageData['created_at'] = $messageData['created_at']->format('Y-m-d\TH:i:s.u');

        $finalUri = $this->prophesize(UriInterface::class);
        $finalUri = $finalUri->reveal();

        $finalUri2 = $this->prophesize(UriInterface::class);
        $finalUri2 = $finalUri2->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('stream/somename')->willReturn($finalUri);
        $uri->withPath('streammetadata/somename')->willReturn($finalUri2);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $request2 = $this->prophesize(RequestInterface::class);
        $request2 = $request2->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUri,
                [
                    'Content-Type' => 'application/vnd.eventstore.atom+json',
                ],
                json_encode([$messageData])
            )
            ->willReturn($request);

        $requestFactory
            ->createRequest(
                'POST',
                $finalUri2,
                [
                    'Content-Type' => 'application/json',
                ],
                json_encode(['some' => 'meta'])
            )
            ->willReturn($request2);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(204)->shouldBeCalled();

        $response2 = $this->prophesize(ResponseInterface::class);
        $response2->getStatusCode()->willReturn(204)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();
        $httpClient->sendRequest($request2)->willReturn($response2->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $messageConverter,
            $httpClient->reveal(),
            $uri->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->create(new Stream(new StreamName('somename'), new \ArrayIterator([$message]), ['some' => 'meta']));
    }

    /**
     * @test
     */
    public function it_creates_stream_and_throws_error_on_400(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('some message');

        $messageConverter = new NoOpMessageConverter();

        $message = TestDomainEvent::with(['foo' => 'bar'], 1);
        $messageData = $messageConverter->convertToArray($message);
        $messageData['created_at'] = $messageData['created_at']->format('Y-m-d\TH:i:s.u');

        $finalUri = $this->prophesize(UriInterface::class);
        $finalUri = $finalUri->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('stream/somename')->willReturn($finalUri);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUri,
                [
                    'Content-Type' => 'application/vnd.eventstore.atom+json',
                ],
                json_encode([$messageData])
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(400)->shouldBeCalled();
        $response->getReasonPhrase()->willReturn('some message')->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $messageConverter,
            $httpClient->reveal(),
            $uri->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->create(new Stream(new StreamName('somename'), new \ArrayIterator([$message]), ['some' => 'meta']));
    }

    /**
     * @test
     */
    public function it_cannot_create_stream_when_not_allowed(): void
    {
        $this->expectException(NotAllowed::class);

        $messageConverter = new NoOpMessageConverter();

        $message = TestDomainEvent::with(['foo' => 'bar'], 1);
        $messageData = $messageConverter->convertToArray($message);
        $messageData['created_at'] = $messageData['created_at']->format('Y-m-d\TH:i:s.u');

        $finalUri = $this->prophesize(UriInterface::class);
        $finalUri = $finalUri->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('stream/somename')->willReturn($finalUri);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUri,
                [
                    'Content-Type' => 'application/vnd.eventstore.atom+json',
                ],
                json_encode([$messageData])
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(405)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $messageConverter,
            $httpClient->reveal(),
            $uri->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->create(new Stream(new StreamName('somename'), new \ArrayIterator([$message]), ['some' => 'meta']));
    }

    /**
     * @test
     */
    public function it_handles_unknown_errors_on_create(): void
    {
        $this->expectException(RuntimeException::class);

        $messageConverter = new NoOpMessageConverter();

        $message = TestDomainEvent::with(['foo' => 'bar'], 1);
        $messageData = $messageConverter->convertToArray($message);
        $messageData['created_at'] = $messageData['created_at']->format('Y-m-d\TH:i:s.u');

        $finalUri = $this->prophesize(UriInterface::class);
        $finalUri = $finalUri->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('stream/somename')->willReturn($finalUri);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUri,
                [
                    'Content-Type' => 'application/vnd.eventstore.atom+json',
                ],
                json_encode([$messageData])
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $messageConverter,
            $httpClient->reveal(),
            $uri->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->create(new Stream(new StreamName('somename'), new \ArrayIterator([$message]), ['some' => 'meta']));
    }

    //</editor-fold>

    //<editor-fold desc="appendTo">

    /**
     * @test
     */
    public function it_appends_to_stream_and_creates_it_automatically(): void
    {
        $messageConverter = new NoOpMessageConverter();

        $message = TestDomainEvent::with(['foo' => 'bar'], 1);
        $messageData = $messageConverter->convertToArray($message);
        $messageData['created_at'] = $messageData['created_at']->format('Y-m-d\TH:i:s.u');

        $finalUri = $this->prophesize(UriInterface::class);
        $finalUri = $finalUri->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('stream/somename')->willReturn($finalUri);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUri,
                [
                    'Content-Type' => 'application/vnd.eventstore.atom+json',
                ],
                json_encode([$messageData])
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(204)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $messageConverter,
            $httpClient->reveal(),
            $uri->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->appendTo(new StreamName('somename'), new \ArrayIterator([$message]));
    }

    //</editor-fold>

    //<editor-fold desc="delete">

    /**
     * @test
     */
    public function it_deletes_stream(): void
    {
        $finalUri = $this->prophesize(UriInterface::class);
        $finalUri = $finalUri->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('delete/somename')->willReturn($finalUri);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUri
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

        $eventStore->delete(new StreamName('somename'));
    }

    /**
     * @test
     */
    public function it_cannot_delete_stream_when_not_found(): void
    {
        $this->expectException(StreamNotFound::class);

        $finalUri = $this->prophesize(UriInterface::class);
        $finalUri = $finalUri->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('delete/somename')->willReturn($finalUri);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUri
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

        $eventStore->delete(new StreamName('somename'));
    }

    /**
     * @test
     */
    public function it_cannot_delete_stream_when_not_allowed(): void
    {
        $this->expectException(NotAllowed::class);

        $finalUri = $this->prophesize(UriInterface::class);
        $finalUri = $finalUri->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('delete/somename')->willReturn($finalUri);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUri
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(405)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
            $uri->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->delete(new StreamName('somename'));
    }

    /**
     * @test
     */
    public function it_handles_unknown_errors_on_delete(): void
    {
        $this->expectException(RuntimeException::class);

        $finalUri = $this->prophesize(UriInterface::class);
        $finalUri = $finalUri->reveal();

        $uri = $this->prophesize(UriInterface::class);
        $uri->withPath('delete/somename')->willReturn($finalUri);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                $finalUri
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
            $uri->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->delete(new StreamName('somename'));
    }

    //</editor-fold>
}
