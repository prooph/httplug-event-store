# Prooph httplug EventStore implementation using HTTP API

[![Build Status](https://travis-ci.org/prooph/httplug-event-store.svg?branch=master)](https://travis-ci.org/prooph/httplug-event-store)
[![Coverage Status](https://coveralls.io/repos/github/prooph/httplug-event-store/badge.svg?branch=master)](https://coveralls.io/github/prooph/httplug-event-store?branch=master)
[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/prooph/improoph)

## Overview

Prooph Event Store is capable of persisting event messages that are organized in streams. `Prooph\EventStore\EventStore`
itself is a facade for different persistence adapters (see the list below) and adds event-driven hook points for `Prooph\EventStore\Plugin\Plugin`s
which make the Event Store highly customizable.

The httplug event store is an implementation that uses httplug to communicate with the [HTTP-API](https://github.com/prooph/event-store-http-api/).

## Usage

This example uses Guzzle6 httplug adapter

```php
$httplug = new \Http\Adapter\Guzzle6\Client();
$eventStore = new \Prooph\EventStore\Httplug($httpPlug, $options);

$streamEvents =$eventStore->load(new StreamName('test-stream'));
```

## Support

- Ask questions on [prooph-users](https://groups.google.com/forum/?hl=de#!forum/prooph) mailing list.
- File issues at [https://github.com/prooph/event-store-http-api/issues](https://github.com/prooph/event-store-http-api/issues).
- Say hello in the [prooph gitter](https://gitter.im/prooph/improoph) chat.

## Contribute

Please feel free to fork and extend existing or add new plugins and send a pull request with your changes!
To establish a consistent code quality, please provide unit tests for all your changes and may adapt the documentation.

## License

Released under the [New BSD License](LICENSE).
