<?php

namespace Cego\FilebeatLogging;

use Monolog\LogRecord;
use Illuminate\Http\Request;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Cache\LaravelCache;
use Monolog\Processor\ProcessorInterface;

class FilebeatHttpContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        // Get the PSR7 request from the laravel container
        dd("wat");
        /** @var ?Request $request */
        $request = app('request'); // TODO test if we need to check if it is bound first.

        dd($request);
        if($request === null) {
            return $record;
        }

        $record->extra = array_merge(
            $record->extra,
            self::httpExtras($request),
            self::urlExtras($request),
            self::userAgentExtras($request),
            self::clientExtras($request)
        );

        return $record;
    }

    private static function clientExtras(Request $request): array
    {
        return [
            'ip'      => $request->header('CF-Connecting-IP') ?? $request->getClientIp(),
            'address' => $request->header('X-Forwarded-For'),
            'geo'     => [
                'country_iso_code' => $request->header('CF-IPCountry'),
            ],
        ];
    }

    private static function httpExtras(Request $request): array
    {
        return [
            'request' => [
                'id'     => $request->header('CF-RAY'),
                'method' => $request->getMethod(),
            ],
        ];
    }

    private static function urlExtras(Request $request): array
    {
        return [
            'path'    => $request->path(),
            'method'  => $request->getMethod(),
            'referer' => $request->header('referer'),
            'domain'  => $request->getHost(),
        ];
    }

    private static function userAgentExtras(Request $request): array
    {
        $userAgent = $request->header('User-Agent');

        if($userAgent === null) {
            return [];
        }

        $headers = $request->headers->all();
        // Cast all headers to string as that is the expected input to client hints factory method.
        $headers = array_map(function ($header) {
            return implode(';', $header);
        }, $headers);

        $clientHints = ClientHints::factory($headers);

        $deviceDetector = new DeviceDetector($userAgent, $clientHints);
        $deviceDetector->setCache(new LaravelCache());

        $deviceDetector->parse();

        return [
            'original' => $userAgent,
            'browser'  => [
                'name'    => $deviceDetector->getClient('name'),
                'version' => $deviceDetector->getClient('version'),
            ],
            'os' => [
                'name'    => $deviceDetector->getOs('name'),
                'version' => $deviceDetector->getOs('version'),
            ],
            'device' => [
                'brand' => $deviceDetector->getBrandName(),
                'model' => $deviceDetector->getModel(),
            ],
        ];
    }
}
