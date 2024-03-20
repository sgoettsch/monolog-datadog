<?php

declare(strict_types=1);

namespace sgoettsch\MonologDatadogTest;

include_once __DIR__ . '/ClientFake.php';

use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Monolog\Level;
use sgoettsch\MonologDatadog\Handler\DatadogHandler;

class LogTest extends \PHPUnit\Framework\TestCase
{
    protected array $data;

    public function testLog(): void
    {
        $client = new ClientFake();

        $responseLog = new Response(202, [], null);
        $client->appendResponse([$responseLog]);

        $apiKey = 'DATADOG-API-KEY';
        $host = 'https://http-intake.logs.datadoghq.com';
        $attributes = [
            'hostname' => 'pipeline',
            'source' => 'php',
            'service' => 'unittest'
        ];

        $logger = new Logger('datadog-channel');

        $datadogLogs = new DatadogHandler($apiKey, $host, $attributes, Level::Info);
        $datadogLogs->setClient($client);

        $logger->pushHandler($datadogLogs);

        $logger->info('i am an info');

        $this->assertEquals(202, $responseLog->getStatusCode());
    }
}
