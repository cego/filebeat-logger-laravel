<?php

namespace Cego\FilebeatLogging;

use Throwable;
use App\Exceptions\Handler;
use Illuminate\Support\Facades\Log;

class LoggerExceptionHandler extends Handler
{
    /**
     * Renders an exception to the console.
     * Will print nothing if using --quiet
     *
     * @param mixed $output
     * @param Throwable $e
     */
    public function renderForConsole($output, Throwable $e): void
    {
        if ($this->isQuiet()) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return;
        }

        parent::renderForConsole($output, $e);
    }

    private function isQuiet(): bool
    {
        $args = $_SERVER['argv'] ?? null ? implode(' ', $_SERVER['argv']) : '';

        return str_contains($args, '--quiet') || str_contains($args, '-q');
    }
}
