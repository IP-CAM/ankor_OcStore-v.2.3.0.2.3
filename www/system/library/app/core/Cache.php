<?php
/**
 * Created by PhpStorm.
 * User: korns
 * Date: 30.10.2017
 * Time: 13:36
 */

namespace app\core;


class Cache{
    public  function __construct(){
        if (!file_exists(CACHE)) {
            mkdir(CACHE);
        }
    }
    public function set($key, $data, $seconds = 3600) {
        $content['data'] = $data;
        $content['end_time'] = time() + $seconds;
        if (file_put_contents(CACHE . '/' . md5($key) . '.txt', serialize($content))) {
            return true;
        } else {
            return false;
        }
    }
    public function get($key) {
        $file = CACHE . '/' . md5($key) . '.txt';
        if (file_exists($file)) {
            $content = unserialize(file_get_contents($file));
            if ($content['end_time'] >= time()) {
                return $content['data'];
            }
            unlink($file);
        }
        return false;
    }
    public function delete($key) {
        $file = CACHE . '/' . md5($key) . '.txt';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}