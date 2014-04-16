<?php

class MenuManagerTemplateProvider implements TemplateGlobalProvider
{
    /**
     * @return array|void
     */
    public static function get_template_global_variables()
    {
        return array(
            'MenuSet' => 'MenuSet'
        );
    }

    /**
     * @param $name
     * @return DataObject
     */
    public static function MenuSet($name)
    {
        return MenuSet::get()
            ->filter(
                array(
                    'Name' => $name
                )
            )->first();
    }
}