<?php

namespace Heyday\MenuManager\TreeField;

use Akqa\SilverStripe\TreeField\Sources\DataObjectTreeSource;
use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use SilverStripe\Forms\Form;
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
     * The tree's labels, in the sentence case the rest of the section uses ("Add menu", "Publish
     * menu") rather than built from the title cased singular name.
     *
     * Every link is a menu item wherever it sits, so adding one at the top level and adding one
     * under another link read the same.
     */
    public function getLabels(): array
    {
        $add = _t(__CLASS__ . '.ADD', 'Add menu item');

        return array_merge(parent::getLabels(), [
            'addRoot' => $add,
            'addChild' => $add,
            'newTitle' => _t(MenuItem::class . '.NEW_ITEM', 'New menu item'),
            'untitled' => _t(__CLASS__ . '.UNTITLED', 'Untitled menu item'),
        ]);
    }

    /**
     * Load the item's own values into its form, not the linked page's.
     *
     * MenuItem falls back to its page for any field it leaves empty, so a blank Link Label would
     * otherwise load as the page's title and be saved as the item's own label on the next save,
     * after which it no longer follows the page. The page's title is offered as a placeholder
     * instead, so it is still clear what a blank label will show.
     */
    public function getNodeForm(DataObject $node, string $name, $controller): Form
    {
        $form = parent::getNodeForm($node, $name, $controller);

        foreach (array_keys(MenuItem::config()->get('db') ?? []) as $fieldName) {
            $field = $form->Fields()->dataFieldByName($fieldName);

            if ($field) {
                $field->setValue($node->getField($fieldName));
            }
        }

        $title = $form->Fields()->dataFieldByName('MenuTitle');
        $page = $node instanceof MenuItem ? $node->Page() : null;

        if ($title && $page && $page->exists()) {
            $title->setAttribute('placeholder', $page->MenuTitle ?: $page->Title);
        }

        return $form;
    }

    /**
     * Overridden to suppress the parent's default placeholder label, because MenuItem provides its
     * own label logic once the member fills the form in.
     */
    protected function extendNewNode(DataObject $node, ?DataObject $parent): void
    {
        // Do nothing
    }
}
