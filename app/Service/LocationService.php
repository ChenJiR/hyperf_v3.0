<?php

namespace App\Service;


use App\Bo\LocationObj;
use App\Component\Cache\RedisCache;
use App\Component\Location\BaiduLocationImpl;
use App\Component\Location\HuaweiLocationImpl;
use Exception;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Context\Context;
use function Hyperf\Support\env;

class LocationService
{

    const CONTEXT_LOCATION_KEY = 'location';

    #[Inject]
    protected BaiduLocationImpl $baiduLocationImpl;

    #[Inject]
    protected HuaweiLocationImpl $huaweiLocationImpl;

    #[Inject]
    protected RedisCache $redisCache;

    public function geoToLocationObj(?string $lng = null, ?string $lat = null): LocationObj
    {
        if (!$lng || !$lat) {
            return LocationObj::instance();
        }
        $lng = number_format($lng, 3);
        $lat = number_format($lat, 3);
        $getLocation = function ($lng, $lat): LocationObj {
            $location = Db::table('location_dict')->where(['lng' => $lng, 'lat' => $lat])->first();
            if (empty($location)) {
                $env = env('APP_ENV', 'prod');
                $location = $env == 'dev'
                    ? ($this->baiduLocationImpl->geoToLocationObj($lng, $lat) ?? $this->huaweiLocationImpl->geoToLocationObj($lng, $lat))
                    : ($this->huaweiLocationImpl->geoToLocationObj($lng, $lat) ?? $this->baiduLocationImpl->geoToLocationObj($lng, $lat));
                $location = $location ?? LocationObj::instance();
                $location_dict = $location->toArray();
                $location_dict['lng'] = $lng;
                $location_dict['lat'] = $lat;
                Db::table('location_dict')->insert($location_dict);
                return $location;
            } else {
                return LocationObj::instance(is_array($location) ? $location : $location->toArray());
            }
        };
        try {
            $cachekey = "location:$lng|$lat";
            $location = $this->redisCache->get($cachekey);
            if (is_array($location) || $location instanceof LocationObj) {
                return is_array($location) ? LocationObj::instance($location) : $location;
            }

            $location = $getLocation($lng, $lat);
            $this->redisCache->set($cachekey, $location->toArray(), 60 * 60 * 24);

            return $location;
        } catch (Exception) {
            return LocationObj::instance();
        }
    }

    public static function setContextLocation(LocationObj $locationObj)
    {
        return Context::set(self::CONTEXT_LOCATION_KEY, $locationObj);
    }

    public static function getContextLocation(): LocationObj
    {
        return Context::get(self::CONTEXT_LOCATION_KEY) ?? LocationObj::instance();
    }

}