<?php

namespace Gurucomkz\DataObjectLogger;

if (class_exists('SilverStripe\\CronTask\\Controllers\\CronTaskController')) {
    class DeleteOldObjectLogsCronTask implements \SilverStripe\CronTask\Interfaces\CronTask
    {

        public function getSchedule()
        {
            return LogUtils::config()->get('purge_cron');
        }

        public function process()
        {
            LogUtils::purgeRecords();
        }
    }
}
