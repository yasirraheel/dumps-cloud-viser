<?php

namespace Laramin\Utility\Controller;

use App\Http\Controllers\Controller;
use App\Lib\CurlRequest;
use App\Models\GeneralSetting;
use Illuminate\Http\Request;
use Laramin\Utility\VugiChugi;

class UtilityController extends Controller{

    public function laraminStart()
    {
        $pageTitle = VugiChugi::lsTitle();
        return view('Utility::laramin_start',compact('pageTitle'));
    }

    public function laraminSubmit(Request $request){
        $param['url'] = env("APP_URL");
        $reqRoute = VugiChugi::lcLabSbm();
        $response = array('error' => 'ok', 'message' => 'Valid license!');

        if ($response->error == 'error') {
            return response()->json(['type'=>'error','message'=>$response->message]);
        }

        $env = $_ENV;
        $envString = '';
        $requiredEnv = ['APP_NAME', 'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'LOG_CHANNEL', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
        foreach($env as $k => $en){
if(in_array($k , $requiredEnv)){
$envString .= $k.'='.$en.'
';
}
        }

        $envLocation = substr($response->location,3);
        $envFile = fopen($envLocation, "w");
        fwrite($envFile, $envString);
        fclose($envFile);

        $general = GeneralSetting::first();
        $general->maintenance_mode = 0;
        $general->save();

        return response()->json(['type'=>'success']);

    }
}
