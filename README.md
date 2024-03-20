# Datadog Monolog integration

Monolog Handler to forward logs to Datadog using async requests.
Uses pcntl for async logging, will automatically fall back to non async requests if pcntl is not available.

## Requirements
- PHP 8.1+
- PHP Curl

## Optional
- php-pcntl ( required for async logging )

## Installation

```shell
composer require sgoettsch/monolog-datadog
```

### Basic Usage

```php
<?php

use Monolog\Logger;
use sgoettsch\MonologDatadog\Handler\DatadogHandler;

$apiKey = 'DATADOG-API-KEY';
$host = 'https://http-intake.logs.datadoghq.com'; // could be set to other domains for example for EU hosted accounts ( https://http-intake.logs.datadoghq.eu )
$attributes = [
    'hostname' => 'YOUR_HOSTNAME',
    'source' => 'php',
    'service' => 'YOUR-SERVICE'
];

$logger = new Logger('datadog-channel');

$datadogLogs = new DatadogHandler($apiKey, $host, $attributes, Monolog\Level::Info);

$logger->pushHandler($datadogLogs);

$logger->info('i am an info');
$logger->warning('i am a warning');
$logger->error('i am an error');
$logger->notice('i am a notice');
$logger->emergency('i am an emergency');
```
