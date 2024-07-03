<?php

namespace Cego\FilebeatLogging;

use Monolog\LogRecord;
use Illuminate\Http\Request;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Cache\LaravelCache;
use Monolog\Processor\ProcessorInterface;

class RequestProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if(app()->runningInConsole()) {
            return $record;
        }

        /** @var ?Request $request */
        $request = app('request');

        if($request === null) {
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
        $deviceDetector->setCache(new ApcuCache());

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
                'isMobile'  => $deviceDetector->isMobile(),
                'isDesktop' => $deviceDetector->isDesktop(),
                'isBot'     => $deviceDetector->isBot(),
                'brand'     => $deviceDetector->getBrandName(),
                'model'     => $deviceDetector->getModel(),
            ],
        ];
    }
}
