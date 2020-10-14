<?php

namespace App\Http\Controllers;
require_once('../vendor/gasparesganga/php-shapefile/src/Shapefile/ShapefileAutoloader.php');
\Shapefile\ShapefileAutoloader::register();

use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Region;
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileReader;


class TestController extends Controller
{
    public function test(){
        try {
            // Open Shapefile
            $storagePath  = \Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();
            $Shapefile = new ShapefileReader($storagePath.'IDM00001.shp');
            $records = [];
            
            // Read all the records
            while ($Geometry = $Shapefile->fetchRecord()) {
                // Skip the record if marked as "deleted"
                if ($Geometry->isDeleted()) {
                    continue;
                }
                // setup for averages
                $pointx = [];
                $pointy = [];
                // get shapefiledata for the record
                $regionData = $Geometry->getDataArray();
                $shapeData = $Geometry->getArray();
                // if the region is only one shape
                if(isset($shapeData['rings'])){
                    // collect the points
                    foreach($shapeData['rings'][0]['points'] as $point){
                        array_push($pointx, $point['x']);
                        array_push($pointy, $point['y']);
                    }
                    // average them out
                    $regionData['long'] = array_sum($pointx) / count($pointx);
                    $regionData['lat'] = array_sum($pointy) / count($pointy);
                }else{
                    $pointxarr = [];
                    $pointyarr = [];
                    $counter = 0;
                    foreach($shapeData['parts'] as $part){
                        // collect the points
                        $tempx = [];
                        $tempy = [];
                        foreach($part['rings'][0]['points'] as $point){
                            array_push($tempx, $point['x']);
                            array_push($tempy, $point['y']);
                        }
                        // average them out
                        $pointxarr[$counter] = array_sum($tempx) / count($tempx);
                        $pointyarr[$counter] = array_sum($tempy) / count($tempy);
                        $counter++;
                    }
                    $regionData['long'] = array_sum($tempx) / count($tempx);
                    $regionData['lat'] = array_sum($tempy) / count($tempy);
                }  
                // Print Geometry as GeoJSON
                array_push($records, $regionData);
            }
            foreach($records as $record){
                $dist = new Region;
                $dist->aac = $record['AAC'];
                $dist->distnum = $record['DIST_NO'];
                $dist->distname = $record['DIST_NAME'];
                $dist->state = $record['STATE_CODE'];
                if(isset($record['long'])){
                    $dist->longitude = $record['long'];
                    $dist->latitude = $record['lat'];
                }
                $dist->save();
            }        
        } catch (ShapefileException $e) {
            // Print detailed error information
            echo "Error Type: " . $e->getErrorType()
                . "\nMessage: " . $e->getMessage()
                . "\nDetails: " . $e->getDetails();
        }

        echo('records read from shapefile');
    }

    public function GetLocationByPostCode($postcode){
        $location = Location::where('postcode', $postcode)->first();
        
        $regions = Region::get();

        // now to work out the distances
        $distances = [];
        foreach($regions as $region){
            $a = $location->latitude - $region->latitude;
            $b = $location->longitude - $region->longitude;
            $distances[$region->id] = sqrt(($a**2) + ($b**2));
        }
        $myRegion = min($distances);
        $region = $regions->find(array_search($myRegion, $distances));
        dd($region->distname);
    }
}
