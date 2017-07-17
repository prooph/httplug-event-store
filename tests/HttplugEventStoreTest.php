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
use Psr\Http\Message\StreamInterface;

class HttplugEventStoreTest extends TestCase
{
    //<editor-fold desc="updateStreamMetadata">

    /**
     * @test
     */
    public function it_updates_stream_metadata(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'streammetadata/somename',
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
            $requestFactory->reveal()
        );

        $eventStore->updateStreamMetadata(new StreamName('somename'), ['some' => 'value']);
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_throws_exception_when_forbidden_to_update_metadata(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'streammetadata/unknown',
                [
                    'Content-Type' => 'application/json',
                ],
                json_encode(['some' => 'value'])
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
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

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'streammetadata/unknown',
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

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'streammetadata/unknown',
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

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $request2 = $this->prophesize(RequestInterface::class);
        $request2 = $request2->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'stream/somename',
                [
                    'Content-Type' => 'application/vnd.eventstore.atom+json',
                ],
                json_encode([$messageData])
            )
            ->willReturn($request);

        $requestFactory
            ->createRequest(
                'POST',
                'streammetadata/somename',
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

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'stream/somename',
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
            $requestFactory->reveal()
        );

        $eventStore->create(new Stream(new StreamName('somename'), new \ArrayIterator([$message]), ['some' => 'meta']));
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_create_stream_when_not_allowed(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $messageConverter = new NoOpMessageConverter();

        $message = TestDomainEvent::with(['foo' => 'bar'], 1);
        $messageData = $messageConverter->convertToArray($message);
        $messageData['created_at'] = $messageData['created_at']->format('Y-m-d\TH:i:s.u');

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'stream/somename',
                [
                    'Content-Type' => 'application/vnd.eventstore.atom+json',
                ],
                json_encode([$messageData])
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $messageConverter,
            $httpClient->reveal(),
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

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'stream/somename',
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

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'stream/somename',
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
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'delete/somename'
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

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'delete/somename'
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
            $requestFactory->reveal()
        );

        $eventStore->delete(new StreamName('somename'));
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_delete_stream_when_not_allowed(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'delete/somename'
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
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

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'POST',
                'delete/somename'
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
            $requestFactory->reveal()
        );

        $eventStore->delete(new StreamName('somename'));
    }

    //</editor-fold>

    //<editor-fold desc="fetchStreamMetadata">

    /**
     * @test
     */
    public function it_fetches_stream_metadata(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'GET',
                'streammetadata/somename',
                [
                    'Accept' => 'application/json',
                ]
            )
            ->willReturn($request);

        $body = $this->prophesize(StreamInterface::class);
        $body->getContents()->willReturn(json_encode(['foo' => 'bar']))->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200)->shouldBeCalled();
        $response->getBody()->willReturn($body->reveal())->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
            $requestFactory->reveal()
        );

        $streamMetadata = $eventStore->fetchStreamMetadata(new StreamName('somename'));

        $this->assertSame(['foo' => 'bar'], $streamMetadata);
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_throws_not_allowed_when_forbidden_to_fetch_stream_metadata(int $forbidenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'GET',
                'streammetadata/somename',
                [
                    'Accept' => 'application/json',
                ]
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbidenStatusCode)->shouldBeCalled();
        $response->getBody()->shouldNotBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->fetchStreamMetadata(new StreamName('somename'));
    }

    /**
     * @test
     */
    public function it_throws_stream_not_found_when_trying_to_fetch_unknown_stream_metadata(): void
    {
        $this->expectException(StreamNotFound::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'GET',
                'streammetadata/somename',
                [
                    'Accept' => 'application/json',
                ]
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(404)->shouldBeCalled();
        $response->getBody()->shouldNotBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->fetchStreamMetadata(new StreamName('somename'));
    }

    //</editor-fold>

    //<editor-fold desc="hasStream">

    /**
     * @test
     */
    public function it_returns_true_when_asking_for_existing_stream(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'GET',
                'has-stream/somename'
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
            $requestFactory->reveal()
        );

        $this->assertTrue($eventStore->hasStream(new StreamName('somename')));
    }

    /**
     * @test
     */
    public function it_returns_false_when_asking_for_non_existing_stream(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'GET',
                'has-stream/somename'
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
            $requestFactory->reveal()
        );

        $this->assertFalse($eventStore->hasStream(new StreamName('somename')));
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_throws_not_allowed_when_forbidden_to_ask_for_existince_of_a_stream(int $forbidenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory
            ->createRequest(
                'GET',
                'has-stream/somename'
            )
            ->willReturn($request);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbidenStatusCode)->shouldBeCalled();

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $eventStore = new HttplugEventStore(
            $this->prophesize(MessageFactory::class)->reveal(),
            $this->prophesize(MessageConverter::class)->reveal(),
            $httpClient->reveal(),
            $requestFactory->reveal()
        );

        $eventStore->hasStream(new StreamName('somename'));
    }

    //</editor-fold>

    public function forbiddenStatusCodes(): array
    {
        return [
            [403],
            [405],
        ];
    }
}
