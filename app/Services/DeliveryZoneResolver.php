<?php

namespace App\Services;

use App\Models\DeliveryZone;
use App\Support\Text;

class DeliveryZoneResolver
{
    public function resolve(?string $neighborhood): ?DeliveryZone
    {
        $wanted = Text::normalize($neighborhood ?? '');

        if ($wanted === '') {
            return null;
        }

        $zones = DeliveryZone::all();

        foreach ($zones as $zone) {
            foreach ($zone->neighborhoods as $name) {
                if (Text::normalize($name) === $wanted) {
                    return $zone;
                }
            }
        }

        $candidates = [];
        foreach ($zones as $zone) {
            foreach ($zone->neighborhoods as $name) {
                $candidates[Text::normalize($name)] = $zone;
            }
        }
        uksort($candidates, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($candidates as $name => $zone) {
            if (str_contains(" {$wanted} ", " {$name} ")) {
                return $zone;
            }
        }

        return null;
    }
}
