<?php

namespace sgoettsch\MonologDatadog\Handler;

use JsonException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\MissingExtensionException;
use Monolog\Level;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class DatadogHandler extends AbstractProcessingHandler
{
    /** @var string Datadog API host */
    private string $host;

    /** @var string Datadog API-Key */
    private string $apiKey;

    /** @var array Datadog optional attributes */
    private array $attributes;

    /** @var Client to overwrite the default client */
    private Client $client;

    /** @var bool use async sending, will always fall back to non async mode if async not available */
    private bool $useAsync = false;

    /**
     * @param string $apiKey Datadog API-Key
     * @param string $host Datadog API host
     * @param array $attributes Datadog optional attributes
     * @param Level $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @param bool $async Use async sending, will always fall back to non async mode if async not available
     * @throws MissingExtensionException
     */
    public function __construct(
        string $apiKey,
        string $host = 'https://http-intake.logs.datadoghq.com',
        array $attributes = [],
        Level $level = Level::Debug,
        bool $bubble = true,
        bool $async = false
    ) {
        if (!extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the DatadogHandler');
        }

        parent::__construct($level, $bubble);

        if (!isset($attributes['traceId'])) {
            $attributes['traceId'] = uniqid();
        }

        $this->apiKey = $apiKey;
        $this->host = $host;
        $this->attributes = $attributes;
        $this->useAsync = $async;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param LogRecord $record
     * @return void
     * @throws JsonException
     */
    protected function write(LogRecord $record): void
    {
        $this->send($record);
    }

    /**
     * Send request to Datadog
     *
     * @param LogRecord $record
     * @throws JsonException
     * @noinspection SpellCheckingInspection
     */
    protected function send(LogRecord $record): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'DD-API-KEY' => $this->apiKey,
        ];

        $source = $this->getSource();
        $hostname = $this->getHostname();
        $traceId = $this->getTraceId();
        $service = $this->getService($record);
        $tags = $this->getTags($record);

        $url = $this->host . '/api/v2/logs';

        $formated = json_decode($record->formatted, true, 512, JSON_THROW_ON_ERROR);
        $logRecord = $record->toArray();

        // Datadog requires the log level in the status attribute.
        $formated['status'] = strtolower($logRecord['level_name']);

        $payLoad = $formated;
        $payLoad['ddsource'] = $source;
        $payLoad['ddtags'] = $tags;
        $payLoad['hostname'] = $hostname;
        $payLoad['trace_id'] = $traceId;
        $payLoad['service'] = $service;

        $client = $this->client ?? new Client();
        $request = new Request('POST', $url, $headers, json_encode($payLoad, JSON_THROW_ON_ERROR));

        if ($this->canAsync()) {
            if ($this->fireAndForget($client, $request)) {
                return;
            }
        }

        $promise = $client->sendAsync($request);
        $promise->wait();
    }

    /**
     * Get Datadog Source from $attributes params.
     *
     * @return string
     */
    protected function getSource(): string
    {
        return $this->attributes['source'] ?? 'php';
    }

    /**
     * Get Datadog Service from $attributes params.
     *
     * @param LogRecord $record
     *
     * @return string
     */
    protected function getService(LogRecord $record): string
    {
        return $this->attributes['service'] ?? $record['channel'];
    }

    /**
     * Get Datadog Hostname from $attributes params.
     *
     * @return string
     */
    protected function getHostname(): string
    {
        return $this->attributes['hostname'] ?? $_SERVER['SERVER_NAME'];
    }

    /**
     * Get Datadog Trace ID from $attributes params.
     *
     * @return string
     */
    protected function getTraceId(): string
    {
        return $this->attributes['traceId'];
    }

    /**
     * Get Datadog Tags from $attributes params.
     *
     * @param LogRecord $record
     *
     * @return string
     */
    protected function getTags(LogRecord $record): string
    {
        $logRecord = $record->toArray();

        $defaultTag = 'level:' . $logRecord['level_name'];

        if (!isset($this->attributes['tags']) || !$this->attributes['tags']) {
            return $defaultTag;
        }

        if (
            (is_array($this->attributes['tags']) || is_object($this->attributes['tags']))
        ) {
            $imploded = implode(',', (array)$this->attributes['tags']);

            return $imploded . ',' . $defaultTag;
        }

        return $defaultTag;
    }

    /**
     * Returns the default formatter to use with this handler
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new JsonFormatter();
    }

    private function canAsync(): bool
    {
        if (!function_exists('pcntl_fork')) {
            return false;
        }

        return $this->useAsync;
    }

    private function fireAndForget(Client $client, Request $request): bool
    {
        if (!function_exists('pcntl_fork')) {
            return false;
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            return false;
        } elseif ($pid === 0) {
            return true;
        } else {
            register_shutdown_function(function ($pid) {
                if (!function_exists('pcntl_waitpid')) {
                    return false;
                }

                // keep the process alive until main finishes, this does not end any shared connections like mysql
                pcntl_waitpid($pid, $status);

                return true;
            }, $pid);
            $this->sendRequest($client, $request);
            exit();
        }
    }

    private function sendRequest(Client $client, Request $request): void
    {
        $promise = $client->sendAsync($request);
        $promise->wait();
    }
}
