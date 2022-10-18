<?php

namespace sgoettsch\MonologDatadog\Formatter;

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use stdClass;

class DatadogFormatter extends JsonFormatter
{
    protected $includeStacktraces = true;

    /**
     * Map Monolog\Logger levels to Datadog's default status type
     */
    private const DATADOG_LEVEL_MAP = [
        Logger::DEBUG => 'info',
        Logger::INFO => 'info',
        Logger::NOTICE => 'warning',
        Logger::WARNING => 'warning',
        Logger::ERROR => 'error',
        Logger::ALERT => 'error',
        Logger::CRITICAL => 'error',
        Logger::EMERGENCY => 'error',
    ];

    public function format(array $record): string
    {
        $normalized = $this->normalize($record);

        if (isset($normalized['context']) && $normalized['context'] === []) {
            $normalized['context'] = new stdClass;
        }

        if (isset($normalized['extra']) && $normalized['extra'] === []) {
            $normalized['extra'] = new stdClass;
        }

        $normalized['status'] = static::DATADOG_LEVEL_MAP[$record['level']];

        return $this->toJson($normalized, true);
    }
}
