<?php

namespace Heyday\MenuManager;

use InvalidArgumentException;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\View\TemplateGlobalProvider;

class MenuManagerTemplateProvider implements TemplateGlobalProvider
{
    use Extensible;

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
     * @return MenuSet|null
     */
    public static function MenuSet($name)
    {
        return Injector::inst()->get(self::class)->findMenuSetByName($name);
    }

    /**
     * Find a MenuSet by name
     *
     * @param string $name
     * @return MenuSet|null
     * @throws InvalidArgumentException
     */
    public function findMenuSetByName($name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Please pass in the name of the MenuSet you're trying to find");
        }
        $result = MenuSet::get()->filter('Name', $name);
        $this->extend('updateFindMenuSetByName', $result);
        return $result->first();
    }
}
