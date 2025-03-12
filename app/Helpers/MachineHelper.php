<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Str;


class MachineHelper
{
    public static function getUniqueMachineIdentifier()
    {
        // You may use any method to obtain a unique identifier for your machine
        // For example, you can use the machine's MAC address
        $macAddress = exec("getmac");
        $machineIdentifier = crc32($macAddress);

        return $machineIdentifier;
    }
}
