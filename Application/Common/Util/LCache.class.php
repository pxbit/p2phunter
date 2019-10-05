<?php
namespace Common\Util;

class LCache {

    /**
     * array_column() // 不支持低版本;
     * 以下方法兼容PHP低版本
     */
    static function array_column(array $array, $column_key, $index_key=null){
        $result = [];
        foreach($array as $arr) {
            if(!is_array($arr)) continue;

            if(is_null($column_key)){
                $value = $arr;
            }else{
                $value = $arr[$column_key];
            }

            if(!is_null($index_key)){
                $key = $arr[$index_key];
                $result[$key] = $value;
            }else{
                $result[] = $value;
            }
        }
        return $result; 
    }

    static function file_put_vardump($file, $val, $append){
        ob_start();
        var_dump($val);
        $content = ob_get_clean();
        if($append)
            file_put_contents($file, $content, FILE_APPEND);
        else
            file_put_contents($file, $content);
    }

    /* thinkphp 的S函数timeout设置没有效果,并且cahce函数已与S函数合并，
     * 这里自行封装成函数. 参数说明：
     * $name:    cache名称;
     * $value:   所要cache的值;
     * $timeout: 超时时间 */
    static function cache($name, $value, $timeout){
        $cache = S($name);
        if(!empty($cache) || !is_null($cache)){
            S($name, NULL);
        }
        S($name, $value);
        S('CACHE_CREATE_TIME'.$name, time());
        S('CACHE_TIMEOUT_'.$name, $timeout);
        return;
    }

    static function getCache($name){
        return S($name);
    }

    static function isCacheValid($name){
        $cache_c = S('CACHE_CREATE_TIME'.$name);
        if(empty($cache_c) || is_null($cache_c) || !isset($cache_c)){
            return false;
        }
        $cache = S($name);
        if(empty($cache) || is_null($cache) || !isset($cache)){
            return false;
        }

        $timediff = time() - $cache_c;
        $timeout  = S('CACHE_TIMEOUT_'.$name);
        if($timediff > $timeout){
            S($name, NULL);
            return false;
        }
        return true;
    }
}

