<?php

namespace Gurucomkz\DataObjectLogger;

use Exception;
use LeKoala\CmsActions\CustomAction;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\View\Parsers\HTML4Value;

/**
 * @property string $Action
 * @property string $ObjectClass
 * @property string $Details
 * @property integer $ObjectID
 * @property integer $MemberID
 * @property-read Member $Member
 */
class ActivityLogEntry extends DataObject
{
    const ACTION_CREATE = 'Create';
    const ACTION_UPDATE = 'Update';
    const ACTION_DELETE = 'Delete';
    const ACTION_PUBLISH = 'Publish';
    const ACTION_UNPUBLISH = 'Unpublish';
    const ACTION_ARCHIVE = 'Archive';
    const ACTION_UNARCHIVE = 'Unarchive';

    private static $table_name = 'ActivityLogEntry';
    private static $excluded_classes = [];

    private static $db = [
        'Action' => "Enum('Create,Update,Delete,Publish,Unpublish,Archive,Unarchive')",
        'ObjectID' => "Int",
        'ObjectClass' => "Varchar",
        'Details' => "Text",
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    private static $indexes = [
        'ActionObject' => [
            'type' => 'index',
            'columns' => [
                'Action',
                'ObjectClass',
                'ObjectID',
            ],
        ],
        'ObjectClassID' => [
            'type' => 'index',
            'columns' => [
                'ObjectClass',
                'ObjectID',
            ],
        ],
        'ObjectClass' => [
            'type' => 'index',
            'columns' => [
                'ObjectClass',
            ],
        ],
        'ObjectID' => [
            'type' => 'index',
            'columns' => [
                'ObjectID',
            ],
        ],
        'Action' => [
            'type' => 'index',
            'columns' => [
                'Action',
            ],
        ],
    ];

    private static $owns = [
    ];

    private static $searchable_fields = [
        'Action' => 'ExactMatchFilter',
        'ObjectClass' => 'ExactMatchFilter',
        'ObjectID' => 'ExactMatchFilter',
    ];

    private static $summary_fields = [
        'Title' => 'Event',
        'Created' => 'Event Time',
    ];

    private static $default_sort = "ID DESC";

    public function getTitle()
    {
        $shortCls = basename(str_replace('\\', '/', $this->ObjectClass));
        $memberName = $this->Member->exists() ? $this->Member->getTitle() : ($this->MemberID ? 'Deleted User' : 'System');
        return "$memberName did '$this->Action' on $shortCls#{$this->ObjectID}";
    }

    public function getDetailsRaw()
    {
        return $this->record['Details'];
    }

    /**
     * @return DataObject
     */
    public function getObject()
    {
        // simulate getComponent() behaviour
        return DataObject::get($this->ObjectClass)->byID($this->ObjectID) ?? Injector::inst()->create($this->ObjectClass);
    }

    public function getDetails()
    {
        return HTML4Value::create('<span style="font-family: Menlo,Monaco,Consolas,Courier New,monospace;white-space: pre;font-size: 0.8em;">' . htmlspecialchars($this->record['Details']) . '</span>');
    }

    public function getDetailsDiff()
    {
        if (!$this->isInDB()) {
            return null;
        }
        $prev = self::get()->filter([
            'ID:LessThan' => $this->ID,
            'ObjectClass' => $this->ObjectClass,
            'ObjectID' => $this->ObjectID,
        ])->sort('ID DESC')->first();
        if (!$prev) {
            return null;
        }
        $json = json_encode(LogUtils::diff($prev->record['Details'], $this->record['Details']), JSON_PRETTY_PRINT);
        return HTML4Value::create('<span style="font-family: Menlo,Monaco,Consolas,Courier New,monospace;white-space: pre;font-size: 0.8em;">' . htmlspecialchars($json) . '</span>');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab('Root.Main', TextField::create('DetailsDiff'), 'Details');
        $fields->replaceField('MemberID', ReadonlyField::create('MemberTitle')->setValue($this->Member->Title));
        return $fields;
    }

    public function scaffoldSearchFields($_params = null)
    {
        $fields = parent::scaffoldSearchFields($_params);

        $allClasses = ClassInfo::dataClassesFor(DataObject::class);
        array_shift($allClasses);
        $classSelectoptions = [
            '' => '',
        ];
        foreach ($allClasses as $cls) {
            $clsSplit = explode('\\', $cls);
            $last = array_pop($clsSplit);
            $clsTitle = $last;
            if (count($clsSplit)) {
                $clsTitle .= ' @ ' . implode('\\', $clsSplit);
            }
            $classSelectoptions[$cls] = $clsTitle;
        }
        sort($classSelectoptions);
        $classSelect = DropdownField::create('ObjectClass', null, $classSelectoptions)->setHasEmptyDefault(true);
        $fields->replaceField('ObjectClass', $classSelect);
        return $fields;
    }


    /**
     * Update Actions
     * @return FieldList
     */
    public function updateCMSActions(FieldList $actions)
    {
        if ($this->canRecover()) {
            $exists = $this->targetObjectExists();
            if (!$exists) {
                $actions->push(
                    CustomAction::create('doRecover', 'Recover')
                );
            }
        }
        return $actions;
    }

    public function canRecover()
    {
        return $this->owner->Action == ActivityLogEntry::ACTION_DELETE && Permission::check(ActivityLogAdmin::PERM_RECOVER);
    }

    public function doRecover()
    {
        if (!$this->canRecover()) {
            throw new Exception('Forbidden');
        }
        $cls = $this->owner->ObjectClass;
        if (!class_exists($cls)) {
            throw new Exception(sprintf('Implementation of %s not available', $cls));
        }
        $exists = $this->getObject()->exists();
        if ($exists) {
            throw new Exception('Object already exists');
        }

        $data = json_decode($this->owner->getDetailsRaw(), true);
        /** @var DataObject */
        $obj = new $cls($data, DataObject::CREATE_MEMORY_HYDRATED);

        $obj->extend('onBeforeRecovery');

        $obj->write(false, true, true);

        $obj->extend('onAfterRecovery');

        $exists = $this->getObject()->exists();
        if ($exists) {
            return sprintf("Recovered '%s' OK", $obj->getTitle());
        } else {
            throw new Exception('Failed to write for unknown reason');
        }
    }

    public function canEdit($var = null)
    {
        return false;
    }
    public function canDelete($var = null)
    {
        return false;
    }
    public function canCreate($var = null, $context = [])
    {
        return false;
    }
}
