<?php
namespace Gurucomkz\DataObjectLogger;

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

    const CREATION_FLAG = '_logger_being_created';

    public function isCreated(?bool $setTo = null)
    {
        if($setTo !== null) {
            return $this->owner->setDynamicData('_logger_being_created', $setTo);
        }
        return $this->owner->getDynamicData('_logger_being_created');
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->classValidToLog()) {
            return; //don't log the log entries :)
        }
        $this->isCreated(!$this->owner->isInDB());
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if (!$this->classValidToLog()) {
            return; //don't log the log entries :)
        }

        if (!$this->isCreated() && !count($this->owner->getChangedFields(false, DataObject::CHANGE_VALUE))) {
            return;
        }
        $this->doLog($this->isCreated() ? ActivityLogEntry::ACTION_CREATE : ActivityLogEntry::ACTION_UPDATE);
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

            $entry->write();
        } catch (\Throwable $e) {
        }
    }
}
