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

namespace Prooph\EventStore\Httplug\Container\Projection;

use Interop\Config\ConfigurationTrait;
use Interop\Config\RequiresConfigId;
use Interop\Config\RequiresMandatoryOptions;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Httplug\Projection\HttplugProjectionManager;
use Psr\Container\ContainerInterface;

final class HttplugProjectionManagerFactory implements
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
     *     HttplugProjectionManager::class => [HttplugProjectionManagerFactory::class, 'service_name'],
     * ];
     * </code>
     *
     * @throws InvalidArgumentException
     */
    public static function __callStatic(string $name, array $arguments): HttplugProjectionManager
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

    public function __invoke(ContainerInterface $container): HttplugProjectionManager
    {
        $config = $container->get('config');
        $config = $this->options($config, $this->configId);

        $requestFactory = null;

        if (isset($config['request_factory'])) {
            $requestFactory = $container->get($config['request_factory']);
        }

        return new HttplugProjectionManager(
            $container->get($config['http_client']),
            $requestFactory
        );
    }

    public function dimensions(): iterable
    {
        return ['prooph', 'projection_manager'];
    }

    public function mandatoryOptions(): iterable
    {
        return [
            'http_client',
        ];
    }
}
