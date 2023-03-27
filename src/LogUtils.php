<?php

namespace Gurucomkz\DataObjectLogger;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;

class LogUtils
{
    const LONG_ID = 'DataObjectLoggerLongDB';

    public static function config()
    {
        return Config::forClass(ActivityLogEntry::class);
    }

    public static function purgeRecords()
    {
        $retain_days = self::config()->get('retain_days');
        $cutoff_time = time() - $retain_days * 86400;

        $tbl = Config::forClass(ActivityLogEntry::class)->get('table_name');
        self::QuickDB()->query(sprintf(
            'DELETE FROM "%s" WHERE Created <= \'%s\'',
            $tbl,
            date('Y-m-d H:i:s', $cutoff_time)
        ));
        self::LongDB()->query(sprintf(
            'DELETE FROM "%s" WHERE Created <= \'%s\'',
            $tbl,
            date('Y-m-d H:i:s', $cutoff_time)
        ));
    }

    public static function QuickDB()
    {
        return DB::get_conn();
    }

    public static function LongDB()
    {
        return DB::get_conn(self::LONG_ID);
    }

    public static function diff($a, $b)
    {
        if (is_string($a)) {
            $a = json_decode($a, true);
        }
        if (is_string($b)) {
            $b = json_decode($b, true);
        }
        if (is_object($a)) {
            $a = (array)$a;
        }
        if (is_object($b)) {
            $b = (array)$b;
        }
        if (!is_array($a)) {
            $a = [];
        }
        if (!is_array($b)) {
            $b = [];
        }
        ksort($a);
        ksort($b);
        $result = [];
        $allKeys = array_unique(array_merge(array_keys($a), array_keys($b)));
        foreach ($allKeys as $k) {
            if (isset($a[$k]) && !isset($b[$k])) {
                $result[$k] = null;
            } elseif (!isset($a[$k]) && isset($b[$k])) {
                $result[$k] = $b[$k];
            } elseif ($b[$k] !== $a[$k]) {
                if ((is_array($a[$k]) || is_object($a[$k]) || empty($a[$k])) && (is_array($b[$k]) || is_object($b[$k]) || empty($b[$k]))) {
                    $result[$k] = self::diff($a[$k], $b[$k]);
                } else {
                    $result[$k] = $b[$k];
                }
            }
        }

        return $result;
    }
}
