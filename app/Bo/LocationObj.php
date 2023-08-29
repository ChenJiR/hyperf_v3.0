<?php

namespace App\Bo;

use App\Service\LocationService;
use App\Util\AbsBO;
use Hyperf\Context\ApplicationContext;

class LocationObj extends AbsBO
{

    const DEFAULT_LNG = '113.625261';
    const DEFAULT_LAT = '34.746426';

    const DEFAULT_PROVINCE = '河南省';
    const DEFAULT_CITY = '郑州市';

    /**
     * 经度
     * @var string
     */
    protected string $lng = self::DEFAULT_LNG;

    /**
     * 纬度
     * @var string
     */
    protected string $lat = self::DEFAULT_LAT;

    /**
     * 省份
     * @var string
     */
    protected string $province = self::DEFAULT_PROVINCE;

    /**
     * 城市
     * @var string
     */
    protected string $city = self::DEFAULT_CITY;

    /**
     * 区县
     * @var string|null
     */
    protected ?string $district;

    /**
     * 街道
     * @var string|null
     */
    protected ?string $street;

    /**
     * 地址
     * @var string|null
     */
    protected ?string $address;

    /**
     * @var int
     */
    protected int $status = 0;

    protected function __construct()
    {
    }


    /**
     * @param array $attributes
     * @return static
     */
    public static function instance(array $attributes = []): static
    {
        if (!isset($attributes['lat']) || !isset($attributes['lng'])) {
            return self::defaultLocation();
        } else if (
            !isset($attributes['province']) ||
            !isset($attributes['city']) ||
            !isset($attributes['district'])
        ) {
            return ApplicationContext::getContainer()->get(LocationService::class)->geoToLocationObj($attributes['lat'], $attributes['lng']);
        } else {
            //济源需要单独处理
            if ($attributes['district'] == '济源市') {
                $attributes['city'] = '济源市';
            }
            return parent::instance($attributes);
        }
    }

    /**
     * 默认郑州
     * @return LocationObj
     */
    private static function defaultLocation(): self
    {
        return self::instance([
            'lat' => self::DEFAULT_LAT,
            'lng' => self::DEFAULT_LNG,
            'province' => self::DEFAULT_PROVINCE,
            'city' => self::DEFAULT_CITY,
            'district' => '中原区',
            'street' => '颍河路',
            'address' => '中国河南省郑州市中原区中原中路233号',
            'status' => 1
        ]);
    }

    public function getLat(): ?string
    {
        return $this->lat;
    }

    public function getLng(): ?string
    {
        return $this->lng;
    }

    public function getProvince(): string
    {
        return $this->province;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

}