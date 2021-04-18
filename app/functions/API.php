<?php

$api = new API();
class API extends Controller
{

    public function validateKey($api_key)
    {
        if($api_key == env('GLOBAL_API_KEY')){
            return true;
        }

        return false;
    }

}