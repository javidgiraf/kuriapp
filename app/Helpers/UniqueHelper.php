<?php

namespace App\Helpers;

use Carbon\Carbon;



class UniqueHelper
{


    public static function UniqueID()
    {
        $currentTimestamp = Carbon::now()->timestamp;

        // Get a unique machine identifier
        $machineIdentifier = MachineHelper::getUniqueMachineIdentifier();

        // Generate a random integer component
        $randomComponent = mt_rand(10000, 90000);

        // Concatenate timestamp, machine identifier, and random integer component
        $uniqueId = $currentTimestamp.$randomComponent;
        // Use $uniqueId in your user registration process
        return $uniqueId;
    }
}
