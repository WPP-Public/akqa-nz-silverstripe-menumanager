<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuSet;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\TextField;

class MenuSetTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    public function testPermission(): void
    {
        $this->logOut();
        $menu = $this->objFromFixture(MenuSet::class, 'header');
        $this->assertFalse($menu->canCreate());
        $this->assertFalse($menu->canEdit());
        $this->assertFalse($menu->canView());
        $this->assertFalse($menu->canDelete());

        $this->logInWithPermission('MANAGE_MENU_SETS');
        $this->assertTrue($menu->canCreate());
        $this->assertTrue($menu->canEdit());
        $this->assertTrue($menu->canView());
        $this->assertTrue($menu->canDelete());

        Config::inst()->merge(MenuSet::class, null, ['default_sets' => [
            'Header'
        ]]);
        $this->assertFalse($menu->canDelete());
    }

    public function testCmsFields(): void
    {
        $menu = $this->objFromFixture(MenuSet::class, 'header');
        $fields = $menu->getCMSFields();

        $this->assertInstanceOf(GridField::class, $fields->dataFieldByName('MenuItems'));
        $this->assertNull($fields->dataFieldByName('Name'));

        $this->assertInstanceOf(
            TextField::class,
            MenuSet::create()->getCMSFields()->dataFieldByName('Name')
        );
    }
}
