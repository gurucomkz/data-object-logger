<?php

namespace Gurucomkz\DataObjectLogger;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Security\PermissionProvider;

class ActivityLogAdmin extends ModelAdmin implements PermissionProvider
{

    private static $url_segment = 'activity';

    private static $menu_title = 'Activity Log';

    private static $menu_priority = -5;

    public $showImportForm = false;

    private static $managed_models = [
        ActivityLogEntry::class,
    ];

    const PERM_RECOVER = 'ActivityLogEntry_RECOVER';

    public function providePermissions()
    {
        return [
            self::PERM_RECOVER => 'Recover Deleted Objects from Activity Log',
        ];
    }

    protected function getGridField(): GridField
    {
        $field = parent::getGridField();

        $this->applyGridFieldState($field);

        return $field;
    }

    public function applyGridFieldState(GridField $gridField)
    {
        $stateInput = $gridField->getState(false);
        $request = $this->getRequest();

        $value = $request->requestVar($gridField->getName());
        if (is_array($value) && isset($value['GridState'])) {
            $stateInput->setValue($value['GridState']);
        }
    }
}
