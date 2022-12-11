<?php

namespace Gurucomkz\DataObjectLogger;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
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
        'Details' => "Text",
    ];
    // private static $searchable_fields = [
    // ];

    private static $has_one = [
        'Object' => DataObject::class,
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

    public function getDetails()
    {
        return HTML4Value::create('<span style="font-family: Menlo,Monaco,Consolas,Courier New,monospace;white-space: pre;font-size: 0.8em;">' . htmlspecialchars($this->record['Details']) . '</span>');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField('MemberID', ReadonlyField::create('MemberTitle')->setValue($this->Member->Title));
        return $fields;
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
