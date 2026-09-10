<?php

namespace Heyday\MenuManager\TreeField;

use Akqa\SilverStripe\TreeField\Sources\DataObjectTreeSource;
use Heyday\MenuManager\MenuItem;
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
     * a design genuinely needs more.
     */
    private static int $max_depth = 3;

    private static string $default_icon = 'font-icon-link';

    /**
     * Give a brand new item a label, so the tree has something to show before the member has
     * filled the form in.
     */
    protected function extendNewNode(DataObject $node, ?DataObject $parent): void
    {
        if (!$node->MenuTitle) {
            $node->MenuTitle = _t(
                MenuItem::class . '.NEW_ITEM',
                'New menu item'
            );
        }
    }
}
