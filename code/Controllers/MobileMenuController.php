<?php

/**
 * Class MobileMenuController
 */
class MobileMenuController extends Controller
{
    /**
     * @var string
     */
    public static $urlSegment = 'menuset';

    /**
     * @return mixed
     */
    public function index()
    {
        $response = $this->getResponse();

        $response->addHeader('Content-Type', 'application/javascript');

        if ($this->cacheInclude = Injector::inst()->get('CacheInclude')) {
            $that = $this;
            $name = 'JSONMenu';

            return $this->cacheInclude->process(
                $name,
                function () use ($that, $name) {
                    return $that->$name();
                },
                Injector::inst()->get('CacheIncludeKeyCreator')
            );
        }
    }

    /**
     * return the JSON for the 2 different cases (Menu Manager or Children)
     *
     * @return string
     */
    public function JSONMenu($mobile = false)
    {
        $menuMobileArray = array();

        $level = $this->getLevel();

        /**
         * There is 2 way to say it's mobile, one for the template and one from an endpoint
         * See on MenuManagerTemplateProvider.php getJsonMenu and getJsonMobileMenu
         */
        if (!$mobile) {
            $mobile = $this->isMobile();
        }

        // if using the menu manager for the menu, get items from there if not, using the children
        if ($this->isUsingMenuManager()) {

            /**
             * You can set mobile at 0 or 1. If it's not mobile, it will return all the MenuSets.
             * If mobile, it'll return the MenuSet selected in the Mobile menu tab.
             * `example.com/menuset?level=2&mobile=0`
             */
            if ($mobile) {
                $menuSets = $this->getMenuSets();
            } else {
                $menuSets = MenuSet::get();
            }

            /** @var MenuSet $menuSet */
            foreach ($menuSets as $menuSet) {
                $menuItems = $menuSet->Children();

                /** @var MenuItem $menuItem */
                foreach ($menuItems as $menuItem) {

                    // Recursive function to grab all the children
                    $children = $this->recursiveChildren($menuItem->Page(), $level);

                    $menuMobileArray[$menuSet->Name][] = [
                        'title' => $menuItem->MenuTitle ?: '',
                        'url' => $menuItem->Link ?: '',
                        'children' => $children
                    ];
                }
            }
        } else {
            // Using the normal Children from the CMS, not using the Mega Menu
            $rootPages = SiteTree::get()
                ->filter([
                    'ParentID' => 0,
                    'ShowInMenus' => 1
                ]);

            foreach ($rootPages as $rootPage) {
                $children = $this->recursiveChildren($rootPage, $level);

                $menuMobileArray['NormalMenu'][] = [
                    'title' => $rootPage->MenuTitle ?: '',
                    'url' => $rootPage->Link() ?: '',
                    'children' => $children
                ];
            }
        }
        return json_encode($menuMobileArray);
    }

    /**
     * Check if the var mobile in request is set to 0 or 1.
     *
     * @return bool
     */
    public function isMobile()
    {
        if ($this->getRequest()->getVar('mobile')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return int|mixed
     */
    public function getLevel()
    {
        if ($this->getRequest()->getVar('level')) {
            $level = $this->getRequest()->getVar('level');
        } else {
            $level = 0;
        }
        return $level;
    }

    /**
     *  Recursive function to find all the children until the end.
     *  Only returning the first level by default
     *  If you want more, you'll need to add a param in the url like the one below.
     *
     *  `example.com/menuset?level=2&mobile=0`
     *
     * @param ArrayList $kids ArrayList of Pages
     * @param int $level
     * @return array
     */
    protected function recursiveChildren($kids, $level = 1)
    {
        $children = [];

        $level--;

        if ($greatKids = $kids->Children()) {
            if ($level > 0) {
                foreach ($greatKids as $greatKid) {
                    $children[] = [
                        'title' => $greatKid->MenuTitle ?: '',
                        'url' => $greatKid->Link() ?: '',
                        'children' => $level > 0 ? $this->recursiveChildren($greatKid, $level) : []
                    ];
                }
            }
        }

        return $children;
    }

    /**
     * Check if it's using Menu manager or the normal children
     * @return mixed
     */
    public function isUsingMenuManager()
    {
        $config = SiteConfig::current_site_config();
        return $config->MenuManagerOption;
    }

    /**
     * Get the MenuSets defined in settings.
     * @return mixed
     */
    public function getMenuSets()
    {
        $config = SiteConfig::current_site_config();
        return $config->MenuSets();
    }
}