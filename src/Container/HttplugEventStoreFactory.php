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

namespace Prooph\EventStore\Httplug\Container;

use Http\Discovery\UriFactoryDiscovery;
use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfigId;
use Interop\Config\RequiresMandatoryOptions;
use Prooph\Common\Messaging\FQCNMessageFactory;
use Prooph\Common\Messaging\NoOpMessageConverter;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Httplug\HttplugEventStore;
use Psr\Container\ContainerInterface;

final class HttplugEventStoreFactory implements
    ProvidesDefaultOptions,
    RequiresConfigId,
    RequiresMandatoryOptions
{
    use ConfigurationTrait;

    /**
     * @var string
     */
    private $configId;

    /**
     * Creates a new instance from a specified config, specifically meant to be used as static factory.
     *
     * In case you want to use another config key than provided by the factories, you can add the following factory to
     * your config:
     *
     * <code>
     * <?php
     * return [
     *     HttplugEventStore::class => [HttplugEventStoreFactory::class, 'service_name'],
     * ];
     * </code>
     *
     * @throws InvalidArgumentException
     */
    public static function __callStatic(string $name, array $arguments): HttplugEventStore
    {
        if (! isset($arguments[0]) || ! $arguments[0] instanceof ContainerInterface) {
            throw new InvalidArgumentException(
                sprintf('The first argument must be of type %s', ContainerInterface::class)
            );
        }

        return (new static($name))->__invoke($arguments[0]);
    }

    public function __construct(string $configId = 'default')
    {
        $this->configId = $configId;
    }

    public function __invoke(ContainerInterface $container): HttplugEventStore
    {
        $config = $container->get('config');
        $config = $this->options($config, $this->configId);

        if (isset($config['uri_factory'])) {
            $uriFactory = $container->get($config['uri_factory']);
        } else {
            $uriFactory = UriFactoryDiscovery::find();
        }

        $requestFactory = null;

        if (isset($config['request_factory'])) {
            $requestFactory = $container->get($config['request_factory']);
        }

        return new HttplugEventStore(
            $container->get($config['message_factory']),
            $container->get($config['message_converter']),
            $container->get($config['http_client']),
            $uriFactory->createUri($config['uri']),
            $requestFactory
        );
    }

    public function dimensions(): iterable
    {
        return ['prooph', 'event_store'];
    }

    public function defaultOptions(): iterable
    {
        return [
            'message_factory' => FQCNMessageFactory::class,
            'message_converter' => NoOpMessageConverter::class,
        ];
    }

    public function mandatoryOptions(): iterable
    {
        return [
            'http_client',
            'uri',
        ];
    }
}
