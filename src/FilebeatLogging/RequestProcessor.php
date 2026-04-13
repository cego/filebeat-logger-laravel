<?php

namespace Cego\FilebeatLogging;

use Monolog\LogRecord;
use Illuminate\Http\Request;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Monolog\Processor\ProcessorInterface;

class RequestProcessor implements ProcessorInterface
{
    private const CACHE_MAX_ENTRIES = 1024;

    /** @var array<string, array<array-key, mixed>> */
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

        $clientHints = ClientHints::factory($headers);
        $cacheKey = $userAgent . "\0" . serialize($clientHints);

        if (isset(self::$userAgentCache[$cacheKey])) {
            // Move to tail so it counts as most-recently-used.
            $cached = self::$userAgentCache[$cacheKey];
            unset(self::$userAgentCache[$cacheKey]);
            self::$userAgentCache[$cacheKey] = $cached;

            return $cached;
        }

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
}
