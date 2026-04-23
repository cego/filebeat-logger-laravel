<?php

namespace Cego\FilebeatLogging;

use Illuminate\Log\LogManager;

/**
 * LogManager decorator that forwards context into the FrankenPHP
 * request-logs extension (\Cego\RequestLogs\context) so fields attached
 * via Log::withContext() / Log::shareContext() also appear on the final
 * access log line emitted by the Go middleware.
 *
 * When the extension is not loaded (e.g. php-fpm, CLI), the forward is
 * a no-op and standard Laravel logging behavior is unchanged.
 */
class ForwardingLogManager extends LogManager
{
    /**
     * @param  array<array-key, mixed>  $context
     *
     * @return $this
     */
    public function shareContext(array $context)
    {
        self::forwardToRequestLogs($context);

        return parent::shareContext($context);
    }

    /**
     * Catches Log::withContext(...) (which reaches the manager via __call
     * and is forwarded to the default driver). Direct channel calls like
     * Log::channel('x')->withContext(...) bypass this and are not forwarded.
     *
     * @param  string  $method
     * @param  array<array-key, mixed>  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($method === 'withContext' && isset($parameters[0]) && is_array($parameters[0])) {
            self::forwardToRequestLogs($parameters[0]);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * @param  array<array-key, mixed>  $context
     */
    private static function forwardToRequestLogs(array $context): void
    {
        if (function_exists('\\Cego\\RequestLogs\\context')) {
            \Cego\RequestLogs\context($context);
        }
    }
}
