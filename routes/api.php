<?php

use App\Models\drivetime;
use App\Models\timecard;
use App\Models\user_preference;
use Illuminate\Support\Facades\Route;
use jbirch8865\AzureAuth\Http\Middleware\AzureAuth;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|

 
*/

Route::group([], function () {
    Route::get('timecards', function () {
        request()->validate([
            'needs' => 'array',
            'needs.*' => 'required_with:needs|integer|min:0|max:18446744073709551615'
        ]);
        $timecards = timecard::whereIn('need_id', request()->input('needs'))->get();
        return response()->json(['message' => 'timecards', 'timecards' => $timecards]);
    })->middleware('throttle:200');
    Route::post('timecards', function () {
        request()->validate([
            'people_id' => 'required|integer|min:0|max:18446744073709551615',
            'need_id' => 'required|integer|min:0|max:18446744073709551615',
            'description' => 'required|string|max:255'
        ]);
        $timecard = new timecard;
        $timecard->people_id = request()->input('people_id');
        $timecard->need_id = request()->input('need_id');
        $timecard->description = request()->input('description');
        $timecard->payroll_status = 0;
        $timecard->save();
        return response()->json(['message' => "added timecard", "timecard" => $timecard]);
    })->middleware('throttle:200');
    Route::delete('timecards/{timecard}', function (App\Models\timecard $timecard) {
        $timecard->forceDelete();
        return response()->json(['message' => "removed timecard", "timecard" => $timecard]);
    })->middleware('throttle:200');
    Route::put('timecards/{timecard}', function (App\Models\timecard $timecard) {
        request()->validate([
            'payroll_status' => "integer|in:0,1,2,3"
        ]);
        $timecard->payroll_status = request()->input('payroll_status');
        $timecard->save();
        return response()->json(['message' => "timecard updated", "timecard" => $timecard]);
    })->middleware('throttle:200');
    Route::get('drivetimes', function () {
        request()->validate([
            'needs' => 'required|array',
            'needs.*' => 'required_with:needs|integer|min:0|max:18446744073709551615',
            'override_google' => 'boolean'
        ]);
        $drivetimes = drivetime::whereIn('need_id', request()->input('needs'))->get();
        $missingTimes = request()->input('needs');
        $returnArray = [];
        if(!request()->input('override_google', false))
        {
            foreach(request()->input('needs') as $need_id)
            {
                foreach($drivetimes as $drivetime)
                {
                    if($drivetime->need_id == $need_id)
                    {
                        $returnArray[] = $drivetime;
                        if (($key = array_search($need_id, $missingTimes)) !== false) {
                            unset($missingTimes[$key]);
                        }
                    }
                }
            }
            $returnArray = array_merge($returnArray,getGoogleDriveTimes($missingTimes));
        }else
        {
            $returnArray = getGoogleDriveTimes($missingTimes);            
        }
        return response()->json(['message' => "drive time", "drivetimes" => $returnArray]);
    })->middleware('throttle:200');
    Route::put('drivetime/{drivetime}', function (App\Models\drivetime $drivetime) {
        request()->validate([
            'actual_time' => 'required_without:actual_distance|integer|min:0',
            'actual_distance' => 'required_without:actual_time|integer|min:0',
            'justification' => 'required|string|max:255'
        ]);
        $id = new AzureAuth;
        $drivetime->actual_time = request()->input('actual_time');
        $drivetime->actual_distance = request()->input('actual_distance');
        $drivetime->justification = request()->input('justification');
        $drivetime->user_override = $id->Get_User_Oid(request());
        $drivetime->last_refreshed = $drivetime->last_refreshed;
        $drivetime->save();
        return response()->json(['message' => 'drivetime updated', 'drivetime' => $drivetime]);
    })->middleware('throttle:200');
    Route::get('userpreferences', function () {
        $id = new AzureAuth;
        $user_pref = user_preference::where('oid',$id->Get_User_Oid(request()))->get();
        return response()->json(['message' => 'current user preferences', 'userpreferences' => $user_pref]);
    });
    Route::post('userpreferences', function () {
        request()->validate(['preference' => 'required|string|max:255','value' => 'required|string|max:255']);
        $id = new AzureAuth;
        $user_pref = new user_preference;
        $user_pref->preference = request()->input('preference');
        $user_pref->oid = $id->Get_User_Oid(request());
        $user_pref->value = request()->input('value');
        $user_pref->save();
        return response()->json(['message' => 'current user preferences', 'userpreferences' => $user_pref]);
    });
    Route::put('userpreferences/{user_preference}', function (App\Models\user_preference $user_preference) {
        request()->validate(['value' => 'required|string|max:255']);
        $user_preference->value = request()->input('value');
        return response()->json(['message' => 'current user preferences', 'userpreferences' => $user_preference]);
    });
});


function getGoogleDriveTimes(array $needs) : array
{
    if(empty($needs))
    {
        return [];
    }
    $driveTimes = [];
    $backendNeeds = [];
    $client = new GuzzleHttp\Client();
    $res = $client->get(env('dispatch_api') . '/api/shifts', [
        'query' => [
            'needs' => $needs,
        ],
        'headers' => [
            'Authorization' => request()->header('Authorization'),
            'Accept'     => 'application/json',
        ]
    ]);
    if ($res->getStatusCode() === 200) {
        $shifts = json_decode($res->getBody())->shifts;
    }
    foreach ($shifts as $shift) {
        foreach ($needs as $need_id) {
            $array = array_filter($shift->shift_has_needs, function ($e) use ($need_id) {
                return $e->id == $need_id;
            });
            if (count($array) > 0) {
                $backendNeeds[] = (object) ["need" => reset($array),"shift" => $shift];
                if (($key = array_search($need_id, $needs)) !== false) {
                    unset($needs[$key]);
                }
                break;
            }
        }
    }
    foreach ($backendNeeds as $backendNeed) {
        //pay as if leaving office
        $client = new GuzzleHttp\Client();
        $res = $client->get(env('unauthenticated_api') . "/api/point_to_point_distance", [
            'query' => [
                'origin' => $backendNeed->need->has_person->office,
                'destination' => $backendNeed->shift->shift_has_address->street_address . " " . $backendNeed->shift->shift_has_address->city . ", " . $backendNeed->shift->shift_has_address->state,
                'depart_at' => $backendNeed->shift->date . " " . $backendNeed->shift->go_time
            ]
        ]);
        if ($res->getStatusCode() === 200) {
            $response = json_decode($res->getBody());
            if (!property_exists($response->distance->rows[0]->elements[0], 'duration_in_traffic')) {
                $duration_in_traffic = $response->distance->rows[0]->elements[0]->duration->value;
            } else {
                $duration_in_traffic = $response->distance->rows[0]->elements[0]->duration_in_traffic->value;
            }
            $office_paid_time_allowable = (($duration_in_traffic * 2 + env('round_paid_drive_time_up_to_nearest_x_in_seconds',900) - (($duration_in_traffic * 2) % env('round_paid_drive_time_up_to_nearest_x_in_seconds',900))) / 60 - env('unpaid_drive_time_in_minutes',60));
            $office_paid_distance_allowable = (ceil($response->distance->rows[0]->elements[0]->distance->value / 1609 * 2) - env('unpaid_drive_distance_in_miles',60));
        } else {
            response()->json(["Error getting distance from persons home office"], 500)->send();
            exit();
        }
        $client = new GuzzleHttp\Client();
        $res = $client->get(env('unauthenticated_api') . "/api/point_to_point_distance", [
            'query' => [
                'origin' => $backendNeed->need->has_person->employee_has_address->street_address . " " . $backendNeed->need->has_person->employee_has_address->city . ", " . $backendNeed->need->has_person->employee_has_address->state,
                'destination' => $backendNeed->shift->shift_has_address->street_address . " " . $backendNeed->shift->shift_has_address->city . ", " . $backendNeed->shift->shift_has_address->state,
                'depart_at' => $backendNeed->shift->date . " " . $backendNeed->shift->go_time
            ]
        ]);
        if ($res->getStatusCode() === 200) {
            $response = json_decode($res->getBody());
            if (!property_exists($response->distance->rows[0]->elements[0], 'duration_in_traffic')) {
                $duration_in_traffic = $response->distance->rows[0]->elements[0]->duration->value;
            } else {
                $duration_in_traffic = $response->distance->rows[0]->elements[0]->duration_in_traffic->value;
            }
            $home_paid_time_allowable = (($duration_in_traffic * 2 + env('round_paid_drive_time_up_to_nearest_x_in_seconds',900) - (($duration_in_traffic * 2) % env('round_paid_drive_time_up_to_nearest_x_in_seconds',900))) / 60 - env('unpaid_drive_time_in_minutes',60));
            $home_paid_distance_allowable = (ceil($response->distance->rows[0]->elements[0]->distance->value / 1609 * 2) - env('unpaid_drive_distance_in_miles',60));
        } else {
            response()->json(["Error getting distance from persons home office"], 500)->send();
            exit();
        }
        $response = json_decode($res->getBody());
        $drivetime = drivetime::where('need_id', $backendNeed->need->id)->first();        
        if ($drivetime === null) {
            $drivetime = new drivetime;
        }
        $drivetime->last_refreshed = date('Y-m-d H:i');
        $drivetime->need_id = $backendNeed->need->id;
        $drivetime->paid_time_allowable = $office_paid_time_allowable;
        $drivetime->paid_distance_allowable = $office_paid_distance_allowable;
        $drivetime->home_drive_time = $home_paid_time_allowable;
        $drivetime->home_drive_distance = $home_paid_distance_allowable;
        $drivetime->actual_distance = null;
        $drivetime->actual_time = null;
        $drivetime->justification = "";
        $drivetime->save();
        $driveTimes[] = $drivetime;
    }
    return $driveTimes;
}
