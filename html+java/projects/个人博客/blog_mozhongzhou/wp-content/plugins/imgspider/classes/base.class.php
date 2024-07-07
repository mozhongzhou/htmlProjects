<?php


class IMGSPY_Base
{

    public static function param($key, $default = '', $type = 'p'){
        if('p' === $type){
            if(isset($_POST[$key])){
                return $_POST[$key];
            }
            return $default;
        } else if ('g' === $type){
            if(isset($_GET[$key])){
                return $_GET[$key];
            }
            return $default;
        }
        if(isset($_POST[$key])){
            return $_POST[$key];
        }
        if(isset($_GET[$key])){
            return $_GET[$key];
        }
        return $default;
    }
    /**
     * @see wpdb
     * @return mixed
     */
    public static function db()
    {
        static $db = null;
        if($db){
            return $db;
        }
        $db = $GLOBALS['wpdb'];
        if($db instanceof wpdb){
            return $db;
        }
        return $db;
    }

    public static function ajax_resp($ret)
    {
        header('content-type:text/json;charset=utf-8');
        echo wp_json_encode($ret);
        exit();
    }

}