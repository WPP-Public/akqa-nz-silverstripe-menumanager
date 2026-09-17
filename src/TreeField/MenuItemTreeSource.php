<?php

namespace Heyday\MenuManager\TreeField;

use Akqa\SilverStripe\TreeField\Sources\DataObjectTreeSource;
use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use SilverStripe\ORM\DataObject;

/**
 * Serves the MenuItems of one MenuSet to a TreeField.
 */
class MenuItemTreeSource extends DataObjectTreeSource
{
    /**
     * The key this source is registered under, and the one MenuSet passes to its TreeField.
     */
    public const KEY = 'menu-items';

    private static string $key = self::KEY;

    private static string $data_class = MenuItem::class;

    private static string $parent_relation = 'ParentItem';

    private static string $scope_relation = 'MenuSet';

    private static string $sort_field = 'Sort';

    /**
     * Menus deeper than three levels are unusable in most designs. Raise it in project config if
     * a design genuinely needs more, or set MenuSet.max_menu_depth to limit a single menu.
     */
    private static int $max_depth = 3;

    private static string $default_icon = '';

    /**
     * The menu being edited can set its own limit through MenuSet.max_menu_depth. Adding and
     * moving links, and the tree in the CMS, all read the limit from here.
     */
    public function getMaxDepth(): int
    {
        $set = $this->getScopeRecord();
        $depth = $set instanceof MenuSet ? $set->getMaxMenuDepth() : null;

        return $depth ?? parent::getMaxDepth();
    }

    /**
     * Give a brand new item a label, so the tree has something to show before the member has
     * filled the form in.
     */
    protected function extendNewNode(DataObject $node, ?DataObject $parent): void
    {
        // Do nothing
    }
}
