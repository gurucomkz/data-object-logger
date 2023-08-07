<?php
namespace Gurucomkz\DataObjectLogger;

use SilverStripe\Core\Environment;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;

/**
 * @property-read DataObject $owner
 */
class LoggingExtension extends DataExtension
{
    public function classValidToLog()
    {
        $excluded = LogUtils::config()->get('excluded_classes');
        return !in_array($this->owner->ClassName, $excluded);
    }
    public function onAfterDelete()
    {
        parent::onAfterDelete();
        if (!$this->classValidToLog()) {
            return; //don't log the log entries :)
        }

        $this->doLog(ActivityLogEntry::ACTION_DELETE);
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->classValidToLog()) {
            return; //don't log the log entries :)
        }
        $this->owner->_logger_being_created = !$this->owner->isInDB();
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if (!$this->classValidToLog()) {
            return; //don't log the log entries :)
        }

        if (!$this->owner->_logger_being_created && !count($this->owner->getChangedFields(null, DataObject::CHANGE_VALUE))) {
            return;
        }
        $this->doLog($this->owner->_logger_being_created ? ActivityLogEntry::ACTION_CREATE : ActivityLogEntry::ACTION_UPDATE);
    }

    public function onAfterArchive()
    {
        if (!$this->classValidToLog()) {
            return; //don't log the log entries :)
        }

        $this->doLog(ActivityLogEntry::ACTION_ARCHIVE);
    }

    public function onAfterPublish()
    {
        if (!$this->classValidToLog()) {
            return; //don't log the log entries :)
        }

        $this->doLog(ActivityLogEntry::ACTION_PUBLISH);
    }

    public function onAfterRevertToLive()
    {
        if (!$this->classValidToLog()) {
            return; //don't log the log entries :)
        }

        $this->doLog(ActivityLogEntry::ACTION_UNARCHIVE);
    }

    private function doLog($action, $details = '')
    {
        try {
            $entry = new ActivityLogEntry();
            if (!$details) {
                $details = json_encode($this->owner->toMap(), JSON_PRETTY_PRINT|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            $entry->Action = $action;
            $entry->Details = $details;
            $entry->ObjectID = $this->owner->ID;
            $entry->ObjectClass = $this->owner->ClassName;

            $user = Security::getCurrentUser();
            $entry->MemberID = $user ? $user->ID : 0;

            if (Environment::getEnv('OBJECT_LOGGER_DEBUG')) {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
                foreach ($backtrace as $traceCall) {
                    if ($traceCall['function'] === 'write') {
                        $entry->CallFile = $traceCall['file'];
                        $entry->CallLine = $traceCall['line'];
                        break;
                    }
                }
            }

            $entry->write();
        } catch (\Throwable $e) {
        }
    }
}
