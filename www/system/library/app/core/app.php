<?php

namespace app\core;
class App{
    /**
     * @var DataStore
     */
    public static $ds;
    /**
     * @var Debug
     */
    public static $debug;
    /**
     * @var Config
     */
    public static $config;
    /**
     * @var \DB
     */
    public static $db;

    public static function Init() {
        App::$ds = new DataStore();
        App::$debug = new Debug();
        App::$config = new Config();
    }


}