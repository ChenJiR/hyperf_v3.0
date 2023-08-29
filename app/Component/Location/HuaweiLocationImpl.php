<?php

namespace App\Component\Location;

use App\Bo\LocationObj;
use App\Component\HttpClient\HuaweiApiHttpClient;
use App\Exception\HttpClientException;
use App\Logger\Log;
use Exception;
use Hyperf\Di\Annotation\Inject;

class HuaweiLocationImpl implements geoToLocation
{

    #[Inject]
    protected HuaweiApiHttpClient $huaweiApiHttpClient;

    public function geoToLocationObj(?string $lng = null, ?string $lat = null): ?LocationObj
    {
        if (!$lat || !$lng) {
            return null;
        }

        try {
            $regeocodes = $this->huaweiApiHttpClient->geoToLocationObj($lng, $lat);
            return LocationObj::instance([
                'lng' => $lng,
                'lat' => $lat,
                'address' => strval($regeocodes['formatted_address']),
                'province' => strval($regeocodes['addressComponent']['province']),
                'city' => empty($regeocodes['addressComponent']['city'])
                    ? strval($regeocodes['addressComponent']['province'])
                    : strval($regeocodes['addressComponent']['city']),
                'district' => empty($regeocodes['addressComponent']['district'])
                    ? '' : strval($regeocodes['addressComponent']['district']),
                'street' => empty($regeocodes['addressComponent']['township'])
                    ? '' : strval($regeocodes['addressComponent']['township']),
            ]);
        } catch (HttpClientException) {
            return null;
        } catch (Exception $e) {
            Log::warning('逆地理编码解析错误:' . $e->getMessage(), ['request_data' => ['lng' => $lng, 'lat' => $lat], 'response' => $regeocodes ?? [], 'line' => $e->getLine()], 'hwapiClient');
            return null;
        }
    }

}