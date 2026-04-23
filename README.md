# FilebeatLogger for Laravel
#### Install via composer
```
composer require cego/filebeat-logger-laravel
```

## FrankenPHP request-log context forwarding

When the app runs under the `cego/frankenphp-image` runtime, this package
transparently forwards context attached via Laravel's `Log` facade into the
FrankenPHP `request-logs` Caddy middleware, so fields like `user.id` appear
on the final access-log line in addition to per-record app logs.

Hooks that forward:

- `Log::withContext([...])` — forwarded to `\Cego\RequestLogs\context()`
  before delegating to the default channel's logger.
- `Log::shareContext([...])` — forwarded before applying to all channels.

Not forwarded: `Log::channel('x')->withContext([...])`. Per-channel context
scoping does not map to a cross-cutting request log entry — use
`Log::withContext()` or `Log::shareContext()` for fields that should land
on the access log.

Outside FrankenPHP (php-fpm, CLI, queue workers), the forward is a no-op
because `\Cego\RequestLogs\context()` is not defined.
