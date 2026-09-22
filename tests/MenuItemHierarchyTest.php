<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testTreeNodeIconMarksItemsOpeningInANewTab(): void
    {
        $item = MenuItem::create(['MenuTitle' => 'Same tab']);

        $this->assertNull($item->getTreeNodeIcon());

        $item->IsNewWindow = true;

        $this->assertSame('font-icon-external-link', $item->getTreeNodeIcon());
    }

    public static function providePhoneAndEmailLinks(): array
    {
        return [
            'phone' => ['tel:+6441234567', 'font-icon-mobile'],
            'phone, upper case' => ['TEL:0800123456', 'font-icon-mobile'],
            'email' => ['mailto:info@example.com', 'font-icon-p-mail'],
            'email, with spaces' => ['  mailto:info@example.com', 'font-icon-p-mail'],
        ];
    }

    #[DataProvider('providePhoneAndEmailLinks')]
    public function testTreeNodeIconMarksPhoneAndEmailLinks(string $link, string $icon): void
    {
        $item = MenuItem::create(['MenuTitle' => 'Contact', 'Link' => $link]);

        $this->assertSame($icon, $item->getTreeNodeIcon());

        // What the link is matters more than where it opens
        $item->IsNewWindow = true;

        $this->assertSame($icon, $item->getTreeNodeIcon());
    }

    public function testTreeNodeIconIgnoresPhoneAndEmailTextThatIsNotTheScheme(): void
    {
        $item = MenuItem::create([
            'MenuTitle' => 'Contact',
            'Link' => 'https://example.com/?next=mailto:info@example.com',
        ]);

        $this->assertNull($item->getTreeNodeIcon());
    }

    public function testOpeningInANewTabIsShownByTheIconAlone(): void
    {
        $item = MenuItem::create(['MenuTitle' => 'Elsewhere', 'Link' => 'https://example.com']);
        $item->IsNewWindow = true;
        $item->write();

        $this->assertSame([], $item->getTreeNodeBadges());
    }
}
