<?php

use SilverStripe\Core\Environment;
use SilverStripe\ORM\DB;

function _DataObjectLoggerLongStorageConfig()
{
    $dbconfig = [
        'type' => 'MySQLDatabase',
        'server' => Environment::getEnv('DATA_OBJECT_LOGGER_LONG_SERVER'),
        'database' => Environment::getEnv('DATA_OBJECT_LOGGER_LONG_NAME'),
        'username' => Environment::getEnv('DATA_OBJECT_LOGGER_LONG_USERNAME'),
        'password' => Environment::getEnv('DATA_OBJECT_LOGGER_LONG_PASSWORD'),
    ];
    
    DB::setConfig($dbconfig, 'longstorage');
}

_DataObjectLoggerLongStorageConfig();
