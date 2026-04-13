<?php

namespace Cego\FilebeatLogging;

use Monolog\LogRecord;
use Illuminate\Http\Request;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Monolog\Processor\ProcessorInterface;

class RequestProcessor implements ProcessorInterface
{
    /**
     * Headers inspected by DeviceDetector\ClientHints::factory(). Included in
     * the parse-result cache key so responses with different client hints do
     * not collide on the same User-Agent string.
     */
    private const CLIENT_HINT_HEADERS = [
        'sec-ch-ua',
        'sec-ch-ua-mobile',
        'sec-ch-ua-platform',
        'sec-ch-ua-platform-version',
        'sec-ch-ua-full-version-list',
        'sec-ch-ua-full-version',
        'sec-ch-ua-model',
        'sec-ch-ua-arch',
        'sec-ch-ua-bitness',
        'sec-ch-ua-wow64',
    ];

    private const CACHE_MAX_ENTRIES = 1024;

    /**
     * Per-worker LRU memoization of userAgentExtras() results keyed on
     * User-Agent + client-hint headers. A single DeviceDetector::parse()
     * call costs several milliseconds even with PreloadCache warm, and
     * this processor runs once per log record — so a request emitting
     * N log lines pays N parses for the same UA without this cache.
     *
     * Ordering relies on PHP arrays preserving insertion order: hits
     * re-insert at the tail, so array_shift() evicts the least-recently-used.
     *
     * @var array<string, array<array-key, mixed>>
     */
    private static array $userAgentCache = [];

    public function __invoke(LogRecord $record): LogRecord
    {
        if (app()->runningInConsole()) {
            return $record;
        }

        /** @var ?Request $request */
        $request = app('request');

        if ($request === null) {
            return $record;
        }

        $record->extra = array_merge(
            $record->extra,
            ['http'       => self::httpExtras($request)],
            ['url'        => self::urlExtras($request)],
            ['user_agent' => self::userAgentExtras($request)],
            ['client'     => self::clientExtras($request)]
        );

        return $record;
    }

    /**
     * @param Request $request
     *
     * @return array<array-key, mixed>
     */
    private static function clientExtras(Request $request): array
    {
        return [
            'ip'      => $request->getClientIp(),
            'address' => $request->header('X-Forwarded-For'),
            'geo'     => [
                'country_iso_code' => $request->header('CF-IPCountry'),
            ],
        ];
    }

    /**
     * @param Request $request
     *
     * @return array<array-key, mixed>
     */
    private static function httpExtras(Request $request): array
    {
        $allowedHeaders = collect(['CF-Ray']);

        return [
            'request' => [
                'id'     => $request->header('CF-RAY'),
                'method' => $request->getMethod(),
                    $allowedHeaders->reduce(function ($carry, $headerName) use ($request) {
                        if (($headerValue = $request->header($headerName)) !== null) {
                            $carry[$headerName] = $headerValue;
                        }

                        return $carry;
                    }, []),
            ],
        ];
    }

    /**
     * @param Request $request
     *
     * @return array<array-key, mixed>
     */
    private static function urlExtras(Request $request): array
    {
        return [
            'path'    => $request->path(),
            'method'  => $request->getMethod(),
            'referer' => $request->header('referer'),
            'domain'  => $request->getHost(),
        ];
    }

    /**
     * @param Request $request
     *
     * @return array<array-key, mixed>
     */
    private static function userAgentExtras(Request $request): array
    {
        $userAgent = $request->header('User-Agent');

        if (is_array($userAgent)) {
            $userAgent = $userAgent[0] ?? null;
        }

        if ($userAgent === null) {
            return [];
        }

        $headers = $request->headers->all();
        // Cast all headers to string as that is the expected input to client hints factory method.
        $headers = array_map(function ($header) {
            return implode(';', $header);
        }, $headers);

        $cacheKey = self::cacheKey($userAgent, $headers);

        if (isset(self::$userAgentCache[$cacheKey])) {
            // Move to tail so it counts as most-recently-used.
            $cached = self::$userAgentCache[$cacheKey];
            unset(self::$userAgentCache[$cacheKey]);
            self::$userAgentCache[$cacheKey] = $cached;

            return $cached;
        }

        $clientHints = ClientHints::factory($headers);

        $deviceDetector = new DeviceDetector($userAgent, $clientHints);
        $deviceDetector->setCache(new PreloadCache());
        $deviceDetector->parse();

        $result = [
            'original' => $userAgent,
            'browser'  => [
                'name'    => $deviceDetector->getClient('name'),
                'version' => $deviceDetector->getClient('version'),
                'isApp'   => strpos($userAgent, 'PlategoApp') !== false,
            ],
            'os' => [
                'name'    => $deviceDetector->getOs('name'),
                'version' => $deviceDetector->getOs('version'),
            ],
            'device' => [
                'isMobile'  => $deviceDetector->isMobile(),
                'isDesktop' => $deviceDetector->isDesktop(),
                'isBot'     => $deviceDetector->isBot(),
                'brand'     => $deviceDetector->getBrandName(),
                'model'     => $deviceDetector->getModel(),
            ],
        ];

        if (count(self::$userAgentCache) >= self::CACHE_MAX_ENTRIES) {
            // Oldest entry sits at the head; hits re-insert at the tail, making this LRU eviction.
            array_shift(self::$userAgentCache);
        }

        self::$userAgentCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * @param string $userAgent
     * @param array<string, string> $headers
     *
     * @return string
     */
    private static function cacheKey(string $userAgent, array $headers): string
    {
        $hintBits = '';

        foreach (self::CLIENT_HINT_HEADERS as $name) {
            if (isset($headers[$name])) {
                $hintBits .= $name . '=' . $headers[$name] . "\n";
            }
        }

        return $userAgent . "\0" . $hintBits;
    }
}
