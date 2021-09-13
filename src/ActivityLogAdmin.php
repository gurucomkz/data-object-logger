<?php

namespace Gurucomkz\DataObjectLogger;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\PermissionProvider;

class ActivityLogAdmin extends ModelAdmin implements PermissionProvider
{

    private static $url_segment = 'activity';

    private static $menu_title = 'Activity Log';

    private static $menu_priority = -5;


    private static $managed_models = [
        ActivityLogEntry::class,
    ];

    public function providePermissions()
    {
        return [
            // 'VIEW_PROMOS' => ['name' => 'View all promos', 'category' => 'Promos management'],
            // 'EDIT_PROMOS' => ['name' => 'Edit or add promos', 'category' => 'Promos management'],

            // 'VIEW_CLAIMS' => ['name' => 'View all claims', 'category' => 'Promos management'],
            // 'EDIT_CLAIMS' => ['name' => 'Edit or add claims', 'category' => 'Promos management'],
        ];
    }
}
