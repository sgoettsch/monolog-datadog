<?php

namespace sgoettsch\MonologDatadog\Formatter;

use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use stdClass;

class DatadogFormatter extends JsonFormatter
{
    protected bool $includeStacktraces = true;

    public function format(LogRecord $record): string
    {
        $normalized = $this->normalize($record);

        if (isset($normalized['context']) && $normalized['context'] === []) {
            $normalized['context'] = new stdClass;
        }

        if (isset($normalized['extra']) && $normalized['extra'] === []) {
            $normalized['extra'] = new stdClass;
        }

        $normalized['status'] = match ($record['level']) {
            Level::Debug, Level::Info => 'info',
            Level::Notice, Level::Warning => 'warning',
            Level::Error, Level::Alert, Level::Critical, Level::Emergency => 'error',
        };

        return $this->toJson($normalized, true);
    }
}
