<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;

class WeatherController extends Controller
{
    // main function to get the request and locate the relevant weather report
    public function GetWeatherByPostCode($postcode){
        $location = Location::where('postcode', $postcode)->first();
        

    }
}
