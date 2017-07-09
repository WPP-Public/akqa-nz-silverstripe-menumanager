<?php

namespace Heyday\MenuManager;

use SilverStripe\ORM\DataObject;
use SilverStripe\View\TemplateGlobalProvider;

class MenuManagerTemplateProvider implements TemplateGlobalProvider
{
    /**
     * @return array
     */
    public static function get_template_global_variables()
    {
        return [
            'MenuSet' => 'MenuSet'
        ];
    }

    /**
     * @param $name
     * @return DataObject
     */
    public static function MenuSet($name)
    {
        return MenuSet::get()
            ->filter(
                [
                    'Name' => $name
                ]
            )->first();
    }
}