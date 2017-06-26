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

    public function getJsonMobileMenu()
    {
        $config = SiteConfig::current_site_config();
        $isMenuManager = $config->MenuManagerOption;

        if ($isMenuManager) {


        } else {
            $rootPages = SiteTree::get()
                ->filter([
                    'ParentID' => 0,
                    'ShowInMenus' => 1
                ]);
        }

    }
}