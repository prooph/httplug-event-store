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

namespace ProophTest\HttplugEventStore\Projection;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Exception\ProjectionNotFound;
use Prooph\EventStore\Httplug\Exception\NotAllowed;
use Prooph\EventStore\Httplug\Projection\HttplugProjectionManager;
use Prooph\EventStore\Projection\ProjectionStatus;
use Prooph\EventStore\Projection\ReadModel;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class HttplugProjectionManagerTest extends TestCase
{
    /**
     * @test
     */
    public function it_cannot_create_query(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $httpClient = $this->prophesize(HttpClient::class);
        $requestFactory = $this->prophesize(RequestFactory::class);

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->createQuery();
    }

    /**
     * @test
     */
    public function it_cannot_create_projection(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $httpClient = $this->prophesize(HttpClient::class);
        $requestFactory = $this->prophesize(RequestFactory::class);

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->createProjection('test');
    }

    /**
     * @test
     */
    public function it_cannot_create_read_model_projection(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $httpClient = $this->prophesize(HttpClient::class);
        $requestFactory = $this->prophesize(RequestFactory::class);
        $readModel = $this->prophesize(ReadModel::class);

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->createReadModelProjection('test', $readModel->reveal());
    }

    //<editor-fold description=deleteProjection">

    /**
     * @test
     */
    public function it_deletes_projection_without_emitted_events(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(204)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/delete/somename/false'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->deleteProjection('somename', false);
    }

    /**
     * @test
     */
    public function it_deletes_projection_with_emitted_events(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(204)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/delete/somename/true'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->deleteProjection('somename', true);
    }

    /**
     * @test
     */
    public function it_cannot_delete_non_existing_projection(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(404)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/delete/somename/true'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->deleteProjection('somename', true);
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_delete_projection_when_forbidden(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/delete/somename/true'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->deleteProjection('somename', true);
    }

    /**
     * @test
     */
    public function it_handles_unknown_error_on_delete_projection(): void
    {
        $this->expectException(\RuntimeException::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/delete/somename/true'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->deleteProjection('somename', true);
    }

    //</editor-fold>

    //<editor-fold description=resetProjection">

    /**
     * @test
     */
    public function it_resets_projection(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(204)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/reset/somename'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->resetProjection('somename');
    }

    /**
     * @test
     */
    public function it_cannot_reset_non_existing_projection(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(404)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/reset/somename'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->resetProjection('somename');
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_reset_projection_when_forbidden(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/reset/somename'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->resetProjection('somename');
    }

    /**
     * @test
     */
    public function it_handles_unknown_error_on_reset_projection(): void
    {
        $this->expectException(\RuntimeException::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/reset/somename'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->resetProjection('somename');
    }

    //</editor-fold>

    //<editor-fold description=stopProjection">

    /**
     * @test
     */
    public function it_stops_projection(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(204)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/stop/somename'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->stopProjection('somename');
    }

    /**
     * @test
     */
    public function it_cannot_stop_non_existing_projection(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(404)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/stop/somename'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->stopProjection('somename');
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_stop_projection_when_forbidden(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/stop/somename'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->stopProjection('somename');
    }

    /**
     * @test
     */
    public function it_handles_unknown_error_on_stop_projection(): void
    {
        $this->expectException(\RuntimeException::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'POST',
            'projection/stop/somename'
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->stopProjection('somename');
    }

    //</editor-fold>

    //<editor-fold description="fetchProjectionNames">

    /**
     * @test
     */
    public function it_fetches_projection_names(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $stream = $this->prophesize(StreamInterface::class);
        $stream->getContents()->willReturn(json_encode(['foo', 'bar']))->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200)->shouldBeCalled();
        $response->getBody()->willReturn($stream->reveal())->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projections?limit=20&offset=0',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionNames = $projectionManager->fetchProjectionNames(null);

        $this->assertSame(['foo', 'bar'], $projectionNames);
    }

    /**
     * @test
     */
    public function it_fetches_projection_names_using_filter_offset_and_limit(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $stream = $this->prophesize(StreamInterface::class);
        $stream->getContents()->willReturn(json_encode(['foo', 'foobar']))->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200)->shouldBeCalled();
        $response->getBody()->willReturn($stream->reveal())->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projections/foo?limit=30&offset=40',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionNames = $projectionManager->fetchProjectionNames('foo', 30, 40);

        $this->assertSame(['foo', 'foobar'], $projectionNames);
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_fetch_projection_names_when_forbidden(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projections?limit=20&offset=0',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionNames(null);
    }

    /**
     * @test
     */
    public function it_handles_unknown_error_on_fetch_projection_names(): void
    {
        $this->expectException(\RuntimeException::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projections?limit=20&offset=0',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionNames(null);
    }

    //</editor-fold>

    //<editor-fold description="fetchProjectionNamesRegex">

    /**
     * @test
     */
    public function it_fetches_projection_names_regex(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $stream = $this->prophesize(StreamInterface::class);
        $stream->getContents()->willReturn(json_encode(['foo', 'foobar']))->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200)->shouldBeCalled();
        $response->getBody()->willReturn($stream->reveal())->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projections-regex/' . urlencode('^foo') . '?limit=20&offset=0',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionNames = $projectionManager->fetchProjectionNamesRegex('^foo');

        $this->assertSame(['foo', 'foobar'], $projectionNames);
    }

    /**
     * @test
     */
    public function it_fetches_projection_names_regex_offset_and_limit(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $stream = $this->prophesize(StreamInterface::class);
        $stream->getContents()->willReturn(json_encode(['foo', 'foobar']))->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200)->shouldBeCalled();
        $response->getBody()->willReturn($stream->reveal())->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projections-regex/' . urlencode('^foo') . '?limit=30&offset=40',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionNames = $projectionManager->fetchProjectionNamesRegex('^foo', 30, 40);

        $this->assertSame(['foo', 'foobar'], $projectionNames);
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_fetch_projection_names_regex_when_forbidden(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projections-regex/' . urlencode('^foo') . '?limit=20&offset=0',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionNamesRegex('^foo');
    }

    /**
     * @test
     */
    public function it_handles_unknown_error_on_fetch_projection_names_regex(): void
    {
        $this->expectException(\RuntimeException::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projections-regex/' . urlencode('^foo') . '?limit=20&offset=0',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionNamesRegex('^foo');
    }

    //</editor-fold>

    //<editor-fold description="fetchStatus">

    /**
     * @test
     */
    public function it_fetches_projection_status(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200)->shouldBeCalled();
        $response->getReasonPhrase()->willReturn(ProjectionStatus::RUNNING()->getName())->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/status/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $status = $projectionManager->fetchProjectionStatus('somename');

        $this->assertTrue(ProjectionStatus::RUNNING()->is($status));
    }

    /**
     * @test
     */
    public function it_cannot_unknown_fetch_projection_status(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(404)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/status/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionStatus('somename');
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_fetch_projection_status_when_forbidden(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/status/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionStatus('somename');
    }

    /**
     * @test
     */
    public function it_handles_unknown_error_on_fetch_projection_status(): void
    {
        $this->expectException(\RuntimeException::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/status/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionStatus('somename');
    }

    //</editor-fold>

    //<editor-fold description="fetchProjectionStreamPositions">

    /**
     * @test
     */
    public function it_fetches_projection_stream_positions(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $stream = $this->prophesize(StreamInterface::class);
        $stream->getContents()->willReturn(json_encode(['stream1' => 200, 'stream2' => 400]))->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200)->shouldBeCalled();
        $response->getBody()->willReturn($stream->reveal())->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/stream-positions/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $streamPositions = $projectionManager->fetchProjectionStreamPositions('somename');

        $this->assertSame(['stream1' => 200, 'stream2' => 400], $streamPositions);
    }

    /**
     * @test
     */
    public function it_cannot_unknown_fetch_projection_stream_positions(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(404)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/stream-positions/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionStreamPositions('somename');
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_fetch_projection_stream_positions_when_forbidden(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/stream-positions/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionStreamPositions('somename');
    }

    /**
     * @test
     */
    public function it_handles_unknown_error_on_fetch_projection_stream_positions(): void
    {
        $this->expectException(\RuntimeException::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/stream-positions/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionStreamPositions('somename');
    }

    //</editor-fold>

    //<editor-fold description="fetchProjectionState">

    /**
     * @test
     */
    public function it_fetches_projection_state(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $stream = $this->prophesize(StreamInterface::class);
        $stream->getContents()->willReturn(json_encode(['foo' => 'bar']))->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200)->shouldBeCalled();
        $response->getBody()->willReturn($stream->reveal())->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/state/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $state = $projectionManager->fetchProjectionState('somename');

        $this->assertSame(['foo' => 'bar'], $state);
    }

    /**
     * @test
     */
    public function it_cannot_unknown_fetch_projection_state(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(404)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/state/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionState('somename');
    }

    /**
     * @test
     * @dataProvider forbiddenStatusCodes
     */
    public function it_cannot_fetch_projection_state_when_forbidden(int $forbiddenStatusCode): void
    {
        $this->expectException(NotAllowed::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn($forbiddenStatusCode)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/state/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionState('somename');
    }

    /**
     * @test
     */
    public function it_handles_unknown_error_on_fetch_projection_state(): void
    {
        $this->expectException(\RuntimeException::class);

        $request = $this->prophesize(RequestInterface::class);
        $request = $request->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(500)->shouldBeCalled();

        $requestFactory = $this->prophesize(RequestFactory::class);
        $requestFactory->createRequest(
            'GET',
            'projection/state/somename',
            [
                'Accept' => 'application/json',
            ]
        )->willReturn($request);

        $httpClient = $this->prophesize(HttpClient::class);
        $httpClient->sendRequest($request)->willReturn($response->reveal())->shouldBeCalled();

        $projectionManager = new HttplugProjectionManager($httpClient->reveal(), $requestFactory->reveal());

        $projectionManager->fetchProjectionState('somename');
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
