<?php

class MenuManagerTemplateProvider implements TemplateGlobalProvider
{
    /**
     * @return array|void
     */
    public static function get_template_global_variables()
    {
        return array(
            'MenuSet' => 'MenuSet',
            'MenuSets' => 'MenuSets'
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

    /**
     * @return DataList
     */
    public static function MenuSets()
    {
        $menuSets = MenuSet::get();

        return $menuSets;
    }
}
