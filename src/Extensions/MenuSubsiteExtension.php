<?php

namespace Heyday\MenuManager\Extensions;

use Heyday\MenuManager\MenuSet;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Subsites\State\SubsiteState;

if (!class_exists('\SilverStripe\Subsites\Model\Subsite') || !class_exists('\SilverStripe\Subsites\State\SubsiteState')) {
    return;
}

class MenuSubsiteExtension extends DataExtension
{
    private static $has_one = [
        'Subsite' => 'SilverStripe\Subsites\Model\Subsite'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->replaceField('SubsiteID', new HiddenField('SubsiteID'));
    }

    public function onBeforeWrite()
    {
        if (!$this->owner->SubsiteID) {
            $this->owner->SubsiteID = \SilverStripe\Subsites\State\SubsiteState::singleton()->getSubsiteId();
        }
    }


    public function requireDefaultRecords()
    {
        if ($this->owner->config()->get('create_menu_sets_per_subsite')) {
            $subsites = \SilverStripe\Subsites\Model\Subsite::get();
            $names = $this->owner->getDefaultSetNames();

            if ($names) {
                foreach ($subsites as $subsite) {
                    $state = \SilverStripe\Subsites\State\SubsiteState::singleton();

                    $state->withState(function () use ($subsite, $names) {
                        \SilverStripe\Subsites\State\SubsiteState::singleton()->setSubsiteId($subsite->ID);

                        foreach ($names as $name) {
                            $existingRecord = MenuSet::get()
                                ->filter([
                                    'Name' => $name,
                                    'SubsiteID' => $subsite->ID
                                ])
                                ->first();

                            if (!$existingRecord) {
                                $set = MenuSet::create();
                                $set->Name = $name;
                                $set->SubsiteID = $subsite->ID;
                                $set->write();
                            }
                        }
                    });
                }
            }
        }
    }
}
