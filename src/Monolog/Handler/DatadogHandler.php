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

    private bool $sendAsync;

    /**
     * @param string $apiKey Datadog API-Key
     * @param string $host Datadog API host
     * @param array $attributes Datadog optional attributes
     * @param Level $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @param bool $sendAsync Whether to send the log asynchronously (requires pcntl extension)
     * @throws MissingExtensionException
     */
    public function __construct(
        string $apiKey,
        string $host = 'https://http-intake.logs.datadoghq.com',
        array $attributes = [],
        Level $level = Level::Debug,
        bool $bubble = true,
        bool $sendAsync = true,
    ) {
        if (!extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the DatadogHandler');
        }

        parent::__construct($level, $bubble);

        $this->apiKey = $apiKey;
        $this->host = $host;
        $this->attributes = $attributes;
        $this->sendAsync = $sendAsync;
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
            'DD-API-KEY' => $this->apiKey
        ];

        $source = $this->getSource();
        $hostname = $this->getHostname();
        $service = $this->getService($record);
        $tags = $this->getTags($record);

        $url = $this->host . '/api/v2/logs';

        $formated = json_decode($record->formatted, true, 512, JSON_THROW_ON_ERROR);

        // Datadog requires the log level in the status attribute.
        $formated['status'] = strtolower($record['level_name']);

        $payLoad = $formated;
        $payLoad['ddsource'] = $source;
        $payLoad['ddtags'] = $tags;
        $payLoad['hostname'] = $hostname;
        $payLoad['service'] = $service;

        $request = new Request('POST', $url, $headers, json_encode($payLoad, JSON_THROW_ON_ERROR));

        if ($this->sendAsync()) {
            $this->sendRequestAsync($request);
        } else {
            $this->sendRequestSync($request);
        }
    }

    /**
     * Send request synchronously
     */
    private function sendRequestSync(Request $request): void
    {
        $client = new Client();
        $client->send($request);
    }

    /**
     * Send request asynchronously
     */
    private function sendRequestAsync(Request $request): void
    {
        $client = new Client();

        if (!function_exists('pcntl_fork')) {
            throw new \RuntimeException('pcntl extension is required for async datadog logging');
        }

        $forkProcess = pcntl_fork();

        if ($forkProcess === -1) {
            throw new \RuntimeException('Failed to fork process for async datadog logging');
        }

        if ($forkProcess !== 0) {
            $promise = $client->sendAsync($request);
            $promise->wait();

            exit(0);
        }
    }

    /**
     * Check if the log should be sent asynchronously
     *
     * @return bool
     */
    private function sendAsync(): bool
    {
        // Requires pcntl extension and pcntl_fork function
        if (!function_exists('pcntl_fork')) {
            return false;
        }

        return $this->sendAsync;
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
     * Get Datadog Tags from $attributes params.
     *
     * @param LogRecord $record
     *
     * @return string
     */
    protected function getTags(LogRecord $record): string
    {
        $defaultTag = 'level:' . $record['level_name'];

        if (!isset($this->attributes['tags']) || !$this->attributes['tags']) {
            return $defaultTag;
        }

        if (
            (is_array($this->attributes['tags']) || is_object($this->attributes['tags']))
            && !empty($this->attributes['tags'])
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
}
