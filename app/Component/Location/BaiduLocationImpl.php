<?php

namespace App\Component\Location;

use App\Bo\LocationObj;
use App\Component\HttpClient\HttpClientComponent;
use App\Exception\LocationException;

class BaiduLocationImpl implements geoToLocation
{

    const BAIDU_GEOCODER_API = 'https://api.map.baidu.com/geocoder';

    public function geoToLocationObj(?string $lng = null, ?string $lat = null): ?LocationObj
    {
        if (!$lat || !$lng) {
            return null;
        }
        try {
            $url = self::BAIDU_GEOCODER_API . "?location=$lat,$lng";
            $result = (new HttpClientComponent($url))->setApiMethod(HttpClientComponent::API_METHOD_GET)
                ->getResult();
            $data = json_decode(json_encode(simplexml_load_string($result)), true);
            if (empty($data['result']['addressComponent']['province']) || empty($data['result']['addressComponent']['city'])) {
                throw new LocationException('经纬度解析错误', $lng, $lat);
            }
            return LocationObj::instance([
                'lng' => $data['result']['location']['lng'],
                'lat' => $data['result']['location']['lat'],
                'address' => strval($data['result']['formatted_address']),
                'province' => strval($data['result']['addressComponent']['province']),
                'city' => strval($data['result']['addressComponent']['city']),
                'district' => strval($data['result']['addressComponent']['district']),
                'street' => empty($data['result']['addressComponent']['street']) ? '' : strval($data['result']['addressComponent']['street']),
            ]);
        } catch (LocationException) {
            return null;
        }
    }
}