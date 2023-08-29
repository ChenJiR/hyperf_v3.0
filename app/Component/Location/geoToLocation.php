<?php

namespace App\Component\Location;

use App\Bo\LocationObj;

interface geoToLocation
{
    /**
     * @param string|null $lng
     * @param string|null $lat
     * @return LocationObj|null
     */
    public function geoToLocationObj(?string $lng = null, ?string $lat = null): ?LocationObj;
}