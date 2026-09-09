<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers menu items nesting under other menu items.
 */
class MenuItemHierarchyTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInWithPermission(['MANAGE_MENU_SETS', 'MANAGE_MENU_ITEMS']);
    }

    private function nest(string $childFixture, string $parentFixture): MenuItem
    {
        $child = $this->objFromFixture(MenuItem::class, $childFixture);
        $child->ParentItemID = $this->objFromFixture(MenuItem::class, $parentFixture)->ID;
        $child->write();

        return $child;
    }

    public function testMenuItemsExcludesNestedItems(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'header');

        $this->assertCount(3, $set->MenuItems());

        $this->nest('header-2', 'header-1');

        $set = MenuSet::get()->byID($set->ID);

        $this->assertCount(2, $set->MenuItems(), 'Nested items drop out of the top level');
        $this->assertCount(3, $set->getAllMenuItems(), 'The menu still owns every item');
    }

    public function testChildItemsAreReturnedInMenuOrder(): void
    {
        $parent = $this->objFromFixture(MenuItem::class, 'header-1');

        $second = $this->nest('header-2', 'header-1');
        $second->Sort = 2;
        $second->write();

        $third = $this->nest('header-3', 'header-1');
        $third->Sort = 1;
        $third->write();

        $titles = MenuItem::get()->byID($parent->ID)->getChildItems()->column('MenuTitle');

        $this->assertSame(['Header 3', 'Header 2'], $titles);
    }

    public function testMenuLevelCountsAncestors(): void
    {
        $this->nest('header-2', 'header-1');
        $this->nest('header-3', 'header-2');

        $this->assertSame(1, $this->objFromFixture(MenuItem::class, 'header-1')->getMenuLevel());
        $this->assertSame(2, MenuItem::get()->byID(
            $this->objFromFixture(MenuItem::class, 'header-2')->ID
        )->getMenuLevel());
        $this->assertSame(3, MenuItem::get()->byID(
            $this->objFromFixture(MenuItem::class, 'header-3')->ID
        )->getMenuLevel());
    }

    public function testDeletingAnItemDeletesItsChildren(): void
    {
        $parent = $this->objFromFixture(MenuItem::class, 'header-1');
        $child = $this->nest('header-2', 'header-1');
        $grandchild = $this->nest('header-3', 'header-2');

        MenuItem::get()->byID($parent->ID)->delete();

        $this->assertNull(MenuItem::get()->byID($parent->ID));
        $this->assertNull(MenuItem::get()->byID($child->ID));
        $this->assertNull(MenuItem::get()->byID($grandchild->ID));
    }

    public function testAsArrayNestsChildren(): void
    {
        $this->nest('header-2', 'header-1');

        $set = MenuSet::get()->byID($this->objFromFixture(MenuSet::class, 'header')->ID);
        $array = $set->asArray();

        $this->assertCount(2, $array['items']);
        $this->assertSame('Header 1', $array['items'][0]['label']);
        $this->assertCount(1, $array['items'][0]['children']);
        $this->assertSame('Header 2', $array['items'][0]['children'][0]['label']);
    }

    public function testTreeNodeBadgesFlagItemsWithoutALink(): void
    {
        $item = MenuItem::create(['MenuTitle' => 'Empty']);
        $item->write();

        $badges = array_column($item->getTreeNodeBadges(), 'text');

        $this->assertContains('No link set', $badges);
    }
}
