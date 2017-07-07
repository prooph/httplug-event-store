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

namespace Prooph\EventStore\Httplug;

use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\RequestFactory;
use Iterator;
use Prooph\Common\Messaging\MessageConverter;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Exception\StreamNotFound;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Psr\Http\Message\UriInterface;

final class HttplugEventStore implements EventStore
{
    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var MessageConverter
     */
    private $messageConverter;

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var RequestFactory
     */
    private $requestFactory;

    public function __construct(
        MessageFactory $messageFactory,
        MessageConverter $messageConverter,
        HttpClient $httpClient,
        UriInterface $uri,
        RequestFactory $requestFactory = null
    ) {
        $this->messageFactory = $messageFactory;
        $this->messageConverter = $messageConverter;
        $this->httpClient = $httpClient;
        $this->uri = $uri;
        $this->requestFactory = $requestFactory ?: MessageFactoryDiscovery::find();
    }

    public function updateStreamMetadata(StreamName $streamName, array $newMetadata): void
    {
        $body = json_encode($newMetadata);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Metadata could not be json encoded');
        }

        $request = $this->requestFactory->createRequest(
            'POST',
            $this->uri->withPath('/streammetadata/' . urlencode($streamName->toString())),
            [
                'Content-Type' => 'application/json',
            ],
            $body
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 204:
                break;
            case 404:
                throw StreamNotFound::with($streamName);
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function create(Stream $stream): void
    {
        $messages = [];

        foreach ($stream->streamEvents() as $event) {
            $message = $this->messageConverter->convertToArray($event);
            $message['created_at'] = $message['created_at']->format('Y-m-d\TH:i:s.u');

            $messages[] = $message;
        }

        $body = json_encode($messages);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Events could not be json encoded');
        }

        $streamName = $stream->streamName();

        $request = $this->requestFactory->createRequest(
            'POST',
            $this->uri->withPath('/stream/' . urlencode($streamName->toString())),
            [
                'Content-Type' => 'application/vnd.eventstore.atom+json',
            ],
            $body
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 204:
                if (! empty($stream->metadata())) {
                    $this->updateStreamMetadata($streamName, $stream->metadata());
                }
                break;
            case 400:
            case 500:
                throw new RuntimeException($response->getReasonPhrase());
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function appendTo(StreamName $streamName, Iterator $streamEvents): void
    {
        $stream = new Stream($streamName, $streamEvents);

        $this->create($stream);
    }

    public function delete(StreamName $streamName): void
    {
        $request = $this->requestFactory->createRequest(
            'POST',
            $this->uri->withPath('/delete/' . urlencode($streamName->toString())),
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 204:
                break;
            case 404:
                throw StreamNotFound::with($streamName);
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function fetchStreamMetadata(StreamName $streamName): array
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->uri->withPath('/streammetadata/' . urlencode($streamName->toString())),
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 200:
                $metadata = json_decode($response->getBody()->getContents());

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Could not json decode response');
                }

                return $metadata;
            case 404:
                throw StreamNotFound::with($streamName);
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function hasStream(StreamName $streamName): bool
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->uri->withPath('/has-stream/' . urlencode($streamName->toString())),
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 200:
                break;
            case 404:
                throw StreamNotFound::with($streamName);
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function load(
        StreamName $streamName,
        int $fromNumber = 1,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator
    {
        // TODO: Implement load() method.
    }

    public function loadReverse(
        StreamName $streamName,
        int $fromNumber = null,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator
    {
        // TODO: Implement loadReverse() method.
    }

    public function fetchStreamNames(
        ?string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array
    {
        // TODO: Implement fetchStreamNames() method.
    }

    public function fetchStreamNamesRegex(
        string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array
    {
        // TODO: Implement fetchStreamNamesRegex() method.
    }

    public function fetchCategoryNames(?string $filter, int $limit = 20, int $offset = 0): array
    {
        // TODO: Implement fetchCategoryNames() method.
    }

    public function fetchCategoryNamesRegex(string $filter, int $limit = 20, int $offset = 0): array
    {
        // TODO: Implement fetchCategoryNamesRegex() method.
    }
}
