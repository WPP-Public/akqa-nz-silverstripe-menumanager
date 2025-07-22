<?php

// phpcs:disable Generic.Files.OneObjectStructurePerFile
// phpcs:disable PSR1.Files.SideEffects

namespace Heyday\MenuManager\Extensions;

use Heyday\MenuManager\MenuSet;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Extension;

if (
    !class_exists('\SilverStripe\Subsites\Model\Subsite') ||
    !class_exists('\SilverStripe\Subsites\State\SubsiteState')
) {
    return;
}

class SubsiteExtension extends Extension
{
    private static $has_many = [
        'MenuSets' => MenuSet::class
    ];

    private static $cascade_deletes = [
        'MenuSets'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('MenuSets');
    }
}
