<p align="center">
    <img src="docs/assets/laraiot-logo.png" alt="LaraIoT logo" width="180">
</p>

<h1 align="center">LaraIoT</h1>

<p align="center">
    A demonstrative and extensible Laravel model for monitoring and controlling IoT equipment.
</p>

<p align="center">
    <a href="https://packagist.org/packages/danpopa/laraiot"><img src="https://img.shields.io/packagist/v/danpopa/laraiot.svg?style=flat-square" alt="Latest Version"></a>
    <a href="https://packagist.org/packages/danpopa/laraiot"><img src="https://img.shields.io/packagist/php-v/danpopa/laraiot.svg?style=flat-square" alt="PHP Version"></a>
    <a href="https://github.com/danpopa26/laraiot/actions"><img src="https://img.shields.io/github/actions/workflow/status/danpopa26/laraiot/tests.yml?branch=main&label=Tests&style=flat-square" alt="Tests"></a>
    <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" alt="MIT License"></a>
</p>

## Overview

LaraIoT provides a demonstrative and extensible model for developing Laravel web applications intended to monitor and control IoT equipment. The package integrates MQTT communication, device and topic management, state persistence, command publishing, and two alternative mechanisms for updating data: Polling and WebSockets.

The package focuses on two real-time communication approachesâ€”Polling and WebSocketsâ€”and allows users to compare their behaviour in the same application context. This makes LaraIoT suitable both as a practical development resource and as a research and teaching project.

LaraIoT can be installed in two ways:

- **Frontend-agnostic installation**, which provides the Laravel backend, MQTT integration, device management and communication services without imposing a frontend technology;
- **Optional Vue.js and Inertia installation**, which adds the ready-to-use LaraIoT web interface when the host application uses a compatible Vue.js, Inertia and Vite stack.

## Project scope and status

LaraIoT is a research and demonstration package. It was created to show how Laravel can be used to build IoT monitoring and control applications and to support a comparison between Polling and WebSocket communication.

The package is not intended to be a professional, production-ready IoT platform. Authentication, authorization, enterprise security hardening and other production requirements are outside the current scope.

The default demonstration WebSocket channel is public. It must not be exposed to an untrusted production environment without an appropriate security layer.

## Why use LaraIoT?

LaraIoT provides a ready-made Laravel foundation for research, education and controlled prototypes. Developers can model devices, configure MQTT topics, persist states, publish commands, and compare Polling with WebSockets without implementing these layers from scratch.

The backend can be installed independently of the optional Vue.js and Inertia interface, or the interface can be added to a compatible host application.

## Features

- physical and logical device management;
- MQTT state and command topics;
- RAW and JSON payload support;
- topic validation and command testing;
- Polling mode for state updates and comparison;
- optional WebSocket mode with Laravel Reverb for real-time updates;
- public `laraiot.devices` broadcast channel for demonstration deployments;
- optional Vue/Inertia administration interface;
- activity logging and application settings.

## Requirements

- PHP 8.3 or later;
- Laravel 11, 12, or 13;
- Composer;
- an MQTT broker for live device communication;
- Node.js 22 or later only when the optional Vue/Inertia interface is installed;
- Laravel Reverb only when WebSocket mode is enabled.

## Installation

```bash
composer require danpopa/laraiot
php artisan laraiot:install
php artisan migrate
```

This is the frontend-agnostic installation. It publishes the LaraIoT configuration and migrations and does not impose a frontend technology. Laravel Reverb is optional; the application remains available in Polling mode when WebSocket support is not configured.

## Optional Vue.js and Inertia interface

```bash
php artisan laraiot:install --ui
npm install
npm run build
```

Use `--force` together with `--ui` to overwrite existing LaraIoT interface files:

```bash
php artisan laraiot:install --ui --force
```

This installation adds the LaraIoT web interface to a host application that already uses a compatible Vue.js, Inertia and Vite frontend stack.

## MQTT listener and commands

Start the MQTT listener:

```bash
php artisan laraiot:mqtt-listen
```

Publish a configured command topic:

```bash
php artisan laraiot:publish {topicId} {commandKey}
```

An optional MQTT client identifier can be supplied:

```bash
php artisan laraiot:publish {topicId} {commandKey} --client-id=client-name
```

## Polling and WebSockets

LaraIoT can operate in Polling mode without Laravel Reverb. When WebSocket mode is selected and Reverb is available, the frontend subscribes to the public `laraiot.devices` channel and consumes `logical-device.state-updated` events. No application user authentication is required for this demonstration channel.

If the WebSocket server is unavailable, the interface falls back to Polling.

## Security notice

LaraIoT should be used for research, education, prototyping and controlled demonstrations. Before using it in production, developers must independently implement and verify authentication, authorization, protected WebSocket channels, encrypted transport, MQTT broker security, secret management, rate limiting and monitoring.

## Configuration

Publish the configuration file when customization is required:

```bash
php artisan vendor:publish --tag="laraiot-config"
```

The published configuration is available at `config/laraiot.php`.

## Testing

Install development dependencies and run the package test suite:

```bash
composer install
vendor/bin/pest
```

Additional quality checks are available through Composer scripts:

```bash
composer lint:check
composer analyse
composer test
```

## Demonstration application

The containerized demonstration application is available in the [`laraiot-app`](https://github.com/danielpopa26/laraiot-app) repository.

## Documentation

- [Romanian documentation](DOCUMENTATIE_LARAIOT_RO.md)
- [Changelog](CHANGELOG.md), when available

## Authors

- **Daniel POPA**, PhD student, Department of Electronics and Telecommunications, Faculty of Electrical Engineering and Information Technology, University of Oradea, Oradea, Romania.
- **Ioan BUCIU**, Professor, PhD, Habilitated Doctor, Department of Electronics and Telecommunications, Faculty of Electrical Engineering and Information Technology, University of Oradea, Oradea, Romania.

## Citation

Please use the metadata in [`CITATION.cff`](CITATION.cff) when citing LaraIoT. A DOI will be added after the first GitHub release is archived by Zenodo.

## License

LaraIoT is open-sourced software licensed under the [MIT license](LICENSE).

## Security

Please do not report security vulnerabilities through public issues. Use the private security reporting mechanism provided by GitHub or contact the maintainers.
