<?php

/**
 * Class MenuManagerTemplateProvider
 */
class MenuManagerTemplateProvider implements TemplateGlobalProvider
{
    /**
     * @return array|void
     */
    public static function get_template_global_variables()
    {
        return array(
            'MenuSet' => 'MenuSet',
            'JsonMenu' => 'JsonMenu',
            'JsonMobileMenu' => 'JsonMobileMenu'
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
     * @return string
     */
    public static function JsonMenu()
    {
        /** @var MobileMenuController $MenuController */
        $MenuController = new MobileMenuController();
        return $MenuController->JSONMenu();
    }

    /**
     * @return string
     */
    public static function JsonMobileMenu()
    {
        $MenuController = new MobileMenuController();
        return $MenuController->JSONMenu(true);
    }
}