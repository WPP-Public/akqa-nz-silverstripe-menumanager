<?php

// phpcs:disable Generic.Files.OneObjectStructurePerFile
// phpcs:disable PSR1.Files.SideEffects
namespace Heyday\MenuManager\Extensions;

use Heyday\MenuManager\MenuSet;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataList;

if (
    !class_exists('\SilverStripe\Subsites\Model\Subsite') ||
    !class_exists('\SilverStripe\Subsites\State\SubsiteState')
) {
    return;
}

class MenuSubsiteExtension extends Extension
{
    private static $has_one = [
        'Subsite' => 'SilverStripe\Subsites\Model\Subsite'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->replaceField('SubsiteID', new HiddenField('SubsiteID'));
    }

    /**
     * Names only need to be unique within a subsite, since templates look them up there
     */
    public function updateMenuSetsSharingNames(DataList &$list)
    {
        $subsiteId = $this->owner->SubsiteID
            ?: \SilverStripe\Subsites\State\SubsiteState::singleton()->getSubsiteId();

        $list = $list->filter('SubsiteID', (int) $subsiteId);
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
