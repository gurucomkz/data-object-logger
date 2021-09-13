<?php

namespace Gurucomkz\DataObjectLogger;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;

class LogUtils
{
    public static function config()
    {
        return Config::forClass(ActivityLogEntry::class);
    }

    public static function purgeRecords()
    {
        $retain_days = self::config()->get('retain_days');
        $cutoff_time = time() - $retain_days * 86400;

        $tbl = Config::forClass(ActivityLogEntry::class)->get('table_name');
        DB::query(sprintf(
            'DELETE FROM "%s" WHERE Created <= \'%s\'',
            $tbl,
            date('Y-m-d H:i:s', $cutoff_time)
        ));
    }
}
