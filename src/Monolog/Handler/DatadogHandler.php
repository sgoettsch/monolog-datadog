<?php

namespace sgoettsch\MonologDatadog\Handler;

use Monolog\Handler\MissingExtensionException;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util;
use Monolog\Formatter\FormatterInterface;
use sgoettsch\MonologDatadog\Formatter\DatadogFormatter;

class DatadogHandler extends AbstractProcessingHandler
{
    /** @var string Datadog API host */
    private string $host;

    /** @var string Datadog API-Key */
    private string $apiKey;

    /** @var array Datadog optional attributes */
    private array $attributes;

    /**
     * @param string $apiKey Datadog API-Key
     * @param string $host Datadog API host
     * @param array $attributes Datadog optional attributes
     * @param int|string $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @throws MissingExtensionException
     */
    public function __construct(
        string $apiKey,
        string $host = 'https://http-intake.logs.datadoghq.com',
        array $attributes = [],
        int|string $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        if (!extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the DatadogHandler');
        }

        parent::__construct($level, $bubble);

        $this->apiKey = $apiKey;
        $this->host = $host;
        $this->attributes = $attributes;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param array $record
     * @return void
     */
    protected function write(array $record): void
    {
        $this->send($record);
    }

    /**
     * Send request to Datadog
     *
     * @param array $record
     */
    protected function send(array $record): void
    {
        $headers = ['Content-Type:application/json'];

        $source = $this->getSource();
        $hostname = $this->getHostname();
        $service = $this->getService($record);
        $tags = $this->getTags($record);

        $url = $this->host . '/v1/input/';
        $url .= $this->apiKey;
        /** @noinspection SpellCheckingInspection */
        $url .= '?ddsource=' . $source . '&service=' . $service . '&hostname=' . $hostname . '&ddtags=' . $tags;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $record['formatted']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        Util::execute($ch);
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
     * @param array $record
     *
     * @return string
     */
    protected function getService(array $record): string
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
     * @param array $record
     *
     * @return string
     */
    protected function getTags(array $record): string
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
     *
     * @return DatadogFormatter
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new DatadogFormatter();
    }
}
