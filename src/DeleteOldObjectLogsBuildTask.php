<?php

namespace Gurucomkz\DataObjectLogger;

use Gurucomkz\DataObjectLogger\LogUtils;

class DeleteOldObjectLogsBuildTask extends \SilverStripe\Dev\BuildTask
{
    private static $segment = "DeleteOldObjectLogs";

    protected $title = "Delete Old Objects Logs";

    public function run($request)
    {
        LogUtils::purgeRecords();
    }
}
