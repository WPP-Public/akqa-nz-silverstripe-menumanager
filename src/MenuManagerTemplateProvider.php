<?php

namespace Heyday\MenuManager;

use InvalidArgumentException;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\TemplateGlobalProvider;

class MenuManagerTemplateProvider implements TemplateGlobalProvider
{
    use Extensible;

    /**
     * @return array
     */
    public static function get_template_global_variables(): array
    {
        return [
            'MenuSet' => 'MenuSet',
            'MenuSets' => 'MenuSets'
        ];
    }

    /**
     * @param $name
     * @return MenuSet|null
     */
    public static function MenuSet($name): ?MenuSet
    {
        return Injector::inst()->get(self::class)->findMenuSetByName($name);
    }

    /**
     * @return MenuSet|null
     */
    public static function MenuSets(): ?DataList
    {
        return MenuSet::get();
    }


    /**
     * Find a MenuSet by name
     *
     * @param string $name
     * @return DataObject|null
     * @throws InvalidArgumentException
     */
    public function findMenuSetByName(string $name): ?DataObject
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Please pass in the name of the MenuSet you're trying to find");
        }

        $result = MenuSet::get()->filter('Name', $name);

        if ($result->exists() && $result->first()->hasExtension('Heyday\MenuManager\Extensions\MenuSubsiteExtension')) {
            $result = $result->where(sprintf(
                'SubsiteID = %s',
                \SilverStripe\Subsites\State\SubsiteState::singleton()->getSubsiteId()
            ));
        }

        $this->extend('updateFindMenuSetByName', $result);

        return $result->first();
    }
}
