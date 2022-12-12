<?php
namespace Gurucomkz\DataObjectLogger;

use LeKoala\CmsActions\CustomLink;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;

/**
 * @property-read DataObject $owner
 */
class LoggingExtension extends DataExtension
{

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSActions(FieldList $actions)
    {
        $cls = str_replace('\\', '-', ActivityLogEntry::class);

        $url = '/admin/activity/?' . http_build_query([
            $cls . '[GridState]' => json_encode([
                'GridFieldFilterHeader' => [
                    'Columns'=>[
                        'ObjectClass' => get_class($this->owner),
                        'ObjectID' => strval($this->owner->ID),
                    ]
                ]
            ])
        ]);

        $historyButton = CustomLink::create('ViewChanges', 'Change log...', $url)
            ->removeExtraClass('btn-info')
            ->setButtonIcon('back-in-time')
            ->setDropUp(true);
        $actions->push($historyButton);

        return $actions;
    }

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

            $entry->write();
        } catch (\Throwable $e) {
        }
    }
}
