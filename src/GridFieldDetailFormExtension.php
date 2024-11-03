<?php
namespace Gurucomkz\DataObjectLogger;

use SilverStripe\Security\Permission;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Forms\LiteralField;
use SilverStripe\View\HTML;

/**
 * @property-read GridFieldDetailForm_ItemRequest $owner
 */
class GridFieldDetailFormExtension extends Extension
{
    public function updateItemEditForm(Form $form)
    {
        if (!$this->classValidToLog()) {
            return;
        }
        if (!Permission::check('CMS_ACCESS_' . ActivityLogAdmin::class)) {
            return;
        }
        /** @var \SilverStripe\ORM\DataObject */
        $record = $this->owner->getRecord();
            
        if (!$record->ID) {
            return;
        }
        $actions = $form->Actions();

        $cls = str_replace('\\', '-', ActivityLogEntry::class);

        $gridState = [
            $cls . '[GridState]' => json_encode([
                'GridFieldFilterHeader' => [
                    'Columns'=>[
                        'ObjectClass' => $record->ClassName,
                        'ObjectID' => strval($record->ID),
                    ]
                ]
            ])
        ];
        $url = '/admin/activity/?' . http_build_query($gridState);

        $historyButton = LiteralField::create(
            'change-log',
            HTML::createTag('a', [
                'href' => $url,
                'title' => 'Change log',
                'aria-label' => 'Change log',
                'target' => '_blank',
                'class' => 'btn btn-warning font-icon-back-in-time btn--circular action--changelog discard-confirmation',
            ])
        );

        $actions->push($historyButton);

        return $actions;
    }

    public function classValidToLog()
    {
        $excluded = LogUtils::config()->get('excluded_classes');
        
        /** @var \SilverStripe\ORM\DataObject */
        $record = $this->owner->getRecord();
        return !in_array($record->ClassName, $excluded);
    }
}
