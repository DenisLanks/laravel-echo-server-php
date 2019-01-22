<?php

namespace Lanks\EchoServer;

class Helper
{
    public static function debug($msg){
        $date = date_format("Y-m-d h:i", date(DATE_ATOM));
        echo ("[$date] $msg" . PHP_EOL);
    }

    public static function error($msg){
        $date = date_format("Y-m-d h:i", date(DATE_ATOM));
        echo ("[$date] Error - $msg" . PHP_EOL);
    }

    public static function toObject($data){
        if(\is_string($data)){
            return json_decode($data); 
        }else{
            $data = json_encode($data);
        }
        return json_decode($data); 
    }
}