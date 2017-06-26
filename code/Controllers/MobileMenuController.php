<?php

/**
 * Class MobileMenuController
 */
class MobileMenuController extends ContentController
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'systemsresults'
    ];

    public function isUsingMenuManager()
    {
        $config = SiteConfig::current_site_config();
        return $config->MenuManagerOption;
    }

    public function getJSONMobileMenu()
    {
        // if using the menu manager for the menu, get items from there if not, using the children
        if (isUsingMenuManager) {

            $menuSets = $this->getMenuSets();

            /** @var MenuSet $menuSet */
            foreach ($menuSets as $menuSet) {

                $menuItems = $menuSet->Children();
            }

            $menuMobileArray = $this->getArrayMobileMenu($menuItems);

        } else {

        }

        json_encode($menuMobileArray);
    }

    public function getMenuSets()
    {
        $config = SiteConfig::current_site_config();
        return $config->MenuSets();
    }
}