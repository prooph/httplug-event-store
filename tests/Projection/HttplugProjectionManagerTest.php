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
use Prooph\EventStore\Projection\ReadModel;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
    public function it_cannot_delete_non_existing_projection_when_forbidden(int $forbiddenStatusCode): void
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
    public function it_cannot_reset_non_existing_projection_when_forbidden(int $forbiddenStatusCode): void
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
    public function it_cannot_stop_non_existing_projection_when_forbidden(int $forbiddenStatusCode): void
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

    public function forbiddenStatusCodes(): array
    {
        return [
            [403],
            [405],
        ];
    }
}
