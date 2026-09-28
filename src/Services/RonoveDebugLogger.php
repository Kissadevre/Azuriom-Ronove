<?php

namespace Azuriom\Plugin\Ronove\Services;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

class RonoveDebugLogger
{
    private ?LoggerInterface $logger = null;

    public function __construct(private readonly RonoveSettings $settings) {}

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if (! $this->settings->debugEnabled()) {
            return;
        }

        $this->logger ??= Log::build(['driver' => 'daily', 'path' => storage_path('logs/ronove-debug.log'), 'level' => 'debug', 'days' => 14, 'replace_placeholders' => true]);
        $this->logger->{$level}('[Ronove] '.$message, $this->sanitize($context));
    }

    private function sanitize(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/(?:api[_-]?key|authorization|cookie|password|secret|token|webhook)/i', $key)) {
                $context[$key] = '[REDACTED]';
            } elseif ($value instanceof Throwable) {
                $context[$key] = ['class' => $value::class, 'message' => $value->getMessage(), 'code' => $value->getCode(), 'file' => $value->getFile(), 'line' => $value->getLine()];
            } elseif (is_array($value)) {
                $context[$key] = $this->sanitize($value);
            }
        }

        return $context;
    }
}
