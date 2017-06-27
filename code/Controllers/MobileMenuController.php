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
     */
    public function JSONMenu()
    {
        $menuMobileArray = array();

        $request = $this->getRequest();

        if ($this->getRequest()->getVar('level')) {
            $level = $request->getVar('level');
        } else {
            $level = 0;
        }

        if ($this->getRequest()->getVar('mobile')) {
            $mobile = $request->getVar('mobile');
        } else {
            $mobile = false;
        }

        // if using the menu manager for the menu, get items from there if not, using the children
        if ($this->isUsingMenuManager()) {

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
     *  Recursive function to find all the children until the end
     *
     * @param $kids
     * @return array
     */
    protected function recursiveChildren($kids, $level = 0)
    {
        $children = [];
        $level--;

        if ($greatKids = $kids->Children()) {
            if ($level > 0){
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

    public function getPageChildren($menuItem)
    {

    }
}