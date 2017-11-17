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

use DateTimeImmutable;
use DateTimeZone;
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
use Prooph\EventStore\Httplug\Exception\NotAllowed;
use Prooph\EventStore\Metadata\FieldType;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Psr\Http\Message\ResponseInterface;

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
     * @var RequestFactory
     */
    private $requestFactory;

    public function __construct(
        MessageFactory $messageFactory,
        MessageConverter $messageConverter,
        HttpClient $httpClient,
        RequestFactory $requestFactory = null
    ) {
        $this->messageFactory = $messageFactory;
        $this->messageConverter = $messageConverter;
        $this->httpClient = $httpClient;
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
            'streammetadata/' . urlencode($streamName->toString()),
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
            case 403:
            case 405:
                throw new NotAllowed();
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
            'stream/' . urlencode($streamName->toString()),
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
                throw new RuntimeException($response->getReasonPhrase());
            case 403:
            case 405:
                throw new NotAllowed();
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
            'delete/' . urlencode($streamName->toString())
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 204:
                break;
            case 404:
                throw StreamNotFound::with($streamName);
            case 403:
            case 405:
                throw new NotAllowed();
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function fetchStreamMetadata(StreamName $streamName): array
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            'streammetadata/' . urlencode($streamName->toString()),
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 200:
                $metadata = json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Could not json decode response');
                }

                return $metadata;
            case 404:
                throw StreamNotFound::with($streamName);
            case 403:
            case 405:
                throw new NotAllowed();
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function hasStream(StreamName $streamName): bool
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            'has-stream/' . urlencode($streamName->toString())
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 200:
                return true;
            case 404:
                return false;
            case 403:
            case 405:
                throw new NotAllowed();
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function load(
        StreamName $streamName,
        int $fromNumber = 1,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator {
        if (null === $count) {
            $count = PHP_INT_MAX;
        }

        $uri = 'stream/' . urlencode($streamName->toString()) . '/' . $fromNumber . '/forward/' . $count
            . '?' . $this->buildQueryFromMetadataMatcher($metadataMatcher);

        $request = $this->requestFactory->createRequest(
            'GET',
            $uri,
            [
                'Accept' => 'application/vnd.eventstore.atom+json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 404:
                throw StreamNotFound::with($streamName);
            case 400:
                throw new InvalidArgumentException($response->getReasonPhrase());
            case 200:
                return $this->createIteratorFromResponse($response);
            case 403:
            case 405:
                throw new NotAllowed();
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function loadReverse(
        StreamName $streamName,
        int $fromNumber = null,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator {
        if (null === $fromNumber) {
            $fromNumber = PHP_INT_MAX;
        }

        if (null === $count) {
            $count = PHP_INT_MAX;
        }

        $uri = 'stream/' . urlencode($streamName->toString()) . '/' . $fromNumber . '/backward/' . $count
            . '?' . $this->buildQueryFromMetadataMatcher($metadataMatcher);

        $request = $this->requestFactory->createRequest(
            'GET',
            $uri,
            [
                'Accept' => 'application/vnd.eventstore.atom+json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 404:
                throw StreamNotFound::with($streamName);
            case 400:
                throw new InvalidArgumentException($response->getReasonPhrase());
            case 200:
                return $this->createIteratorFromResponse($response);
            case 403:
            case 405:
                throw new NotAllowed();
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function fetchStreamNames(
        ?string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array {
        $limitPart = 'limit=' . $limit . '&offset=' . $offset;

        $query = $this->buildQueryFromMetadataMatcher($metadataMatcher);

        if ($query === '') {
            $query = $limitPart;
        } else {
            $query .= '&' . $limitPart;
        }

        if (null !== $filter) {
            $uri = 'streams/' . urlencode($filter) . '?' . $query;
        } else {
            $uri = 'streams?' . $query;
        }

        $request = $this->requestFactory->createRequest(
            'GET',
            $uri,
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 403:
            case 405:
                throw new NotAllowed();
            case 200:
                $streamNames = json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Could not json decode response');
                }

                return $streamNames;
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function fetchStreamNamesRegex(
        string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array {
        $limitPart = 'limit=' . $limit . '&offset=' . $offset;

        $query = $this->buildQueryFromMetadataMatcher($metadataMatcher);

        if ($query === '') {
            $query = $limitPart;
        } else {
            $query .= '&' . $limitPart;
        }

        $uri = 'streams-regex/' . urlencode($filter) . '?' . $query;

        $request = $this->requestFactory->createRequest(
            'GET',
            $uri,
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 403:
            case 405:
                throw new NotAllowed();
            case 200:
                $streamNames = json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Could not json decode response');
                }

                return $streamNames;
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function fetchCategoryNames(?string $filter, int $limit = 20, int $offset = 0): array
    {
        $query = 'limit=' . $limit . '&offset=' . $offset;

        if (null !== $filter) {
            $uri = 'categories/' . urlencode($filter) . '?' . $query;
        } else {
            $uri = 'categories?' . $query;
        }

        $request = $this->requestFactory->createRequest(
            'GET',
            $uri,
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 403:
            case 405:
                throw new NotAllowed();
            case 200:
                $categories = json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Could not json decode response');
                }

                return $categories;
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function fetchCategoryNamesRegex(string $filter, int $limit = 20, int $offset = 0): array
    {
        $uri = 'categories-regex/' . urlencode($filter) . '?limit=' . $limit . '&offset=' . $offset;

        $request = $this->requestFactory->createRequest(
            'GET',
            $uri,
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 403:
            case 405:
                throw new NotAllowed();
            case 200:
                $categories = json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Could not json decode response');
                }

                return $categories;
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    private function buildQueryFromMetadataMatcher(MetadataMatcher $metadataMatcher = null): string
    {
        if (null === $metadataMatcher) {
            return '';
        }

        $params = [];

        foreach ($metadataMatcher->data() as $key => $match) {
            if (FieldType::METADATA()->is($match['fieldType'])) {
                $prefix = 'meta_' . $key . '_';
            } else {
                $prefix = 'property_' . $key . '_';
            }

            $params[] = $prefix . 'field=' . $match['field'];
            $params[] = $prefix . 'operator=' . $match['operator']->getName();
            $params[] = $prefix . 'value=' . $match['value'];
        }

        return implode('&', $params);
    }

    private function createIteratorFromResponse(ResponseInterface $response): Iterator
    {
        $data = json_decode($response->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Could not json decode response from event store');
        }

        foreach ($data['entries'] as $entry) {
            $entry['created_at'] = DateTimeImmutable::createFromFormat(
                'Y-m-d\TH:i:s.u',
                $entry['created_at'],
                new DateTimeZone('UTC')
            );

            if (! $entry['created_at'] instanceof DateTimeImmutable) {
                throw new RuntimeException('Could not create DateTimeImmutable object from event data');
            }

            yield $this->messageFactory->createMessageFromArray($entry['message_name'], $entry);
        }
    }
}
