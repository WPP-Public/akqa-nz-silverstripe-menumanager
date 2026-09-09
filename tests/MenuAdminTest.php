<?php

namespace Heyday\MenuManager\Test;

use Akqa\SilverStripe\TreeField\Form\TreeField;
use Heyday\MenuManager\MenuAdmin;
use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;

class MenuAdminTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    private function makeAdmin(array $vars = []): MenuAdmin
    {
        $admin = Injector::inst()->create(MenuAdmin::class);
        $request = new HTTPRequest('GET', '/admin/menu-manager', $vars);
        $request->setSession(new Session([]));

        // Extensions on the admin resolve the current request from the container
        Injector::inst()->registerService($request, HTTPRequest::class);

        $admin->setRequest($request);
        $admin->doInit();

        return $admin;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cookie::force_expiry(MenuAdmin::SET_COOKIE);
        $this->logInWithPermission(['CMS_ACCESS', 'MANAGE_MENU_SETS', 'MANAGE_MENU_ITEMS']);
    }

    public function testEditFormShowsOneMenuAtATime(): void
    {
        $fields = $this->makeAdmin()->getEditForm()->Fields();
        $tree = $fields->dataFieldByName('MenuItems');

        $this->assertInstanceOf(TreeField::class, $tree);
        $this->assertSame(MenuItemTreeSource::KEY, $tree->getSourceKey());
        $this->assertNotNull(
            $tree->getScopeID(),
            'The tree is scoped to a single menu rather than showing them all'
        );
    }

    public function testMenuIsChosenByRequest(): void
    {
        $footer = $this->objFromFixture(MenuSet::class, 'footer');

        $admin = $this->makeAdmin(['MenuSetID' => (string) $footer->ID]);

        $this->assertSame($footer->ID, $admin->getCurrentMenuSet()->ID);
        $this->assertSame(
            $footer->ID,
            (int) $admin->getEditForm()->Fields()->dataFieldByName('MenuItems')->getScopeID()
        );
    }

    public function testAnUnknownMenuInTheRequestIsIgnored(): void
    {
        $admin = $this->makeAdmin(['MenuSetID' => '999999']);

        $this->assertNotNull($admin->getCurrentMenuSet());
        $this->assertNotSame(999999, $admin->getCurrentMenuSet()->ID);
    }

    public function testTheChosenMenuIsRemembered(): void
    {
        $footer = $this->objFromFixture(MenuSet::class, 'footer');

        $this->makeAdmin(['MenuSetID' => (string) $footer->ID])->getCurrentMenuSet();

        $this->assertSame((string) $footer->ID, Cookie::get(MenuAdmin::SET_COOKIE));
        $this->assertSame($footer->ID, $this->makeAdmin()->getCurrentMenuSet()->ID);
    }

    public function testWithoutAChoiceTheMostRecentlyEditedMenuOpens(): void
    {
        $header = $this->objFromFixture(MenuSet::class, 'header');
        $footer = $this->objFromFixture(MenuSet::class, 'footer');

        DBDatetime::set_mock_now('2026-01-01 09:00:00');
        $header->forceChange();
        $header->write();

        DBDatetime::set_mock_now('2026-01-02 09:00:00');
        $footer->forceChange();
        $footer->write();
        DBDatetime::clear_mock_now();

        $this->assertSame($footer->ID, $this->makeAdmin()->getCurrentMenuSet()->ID);
    }

    public function testSelectorListsEveryMenu(): void
    {
        $selector = $this->makeAdmin()->getEditForm()->Fields()->dataFieldByName('MenuSetID');

        $this->assertNotNull($selector);
        $this->assertCount(2, $selector->getSource());
    }

    public function testEditFormExplainsItselfWithoutPermission(): void
    {
        $this->logInWithPermission(['CMS_ACCESS']);

        $fields = $this->makeAdmin()->getEditForm()->Fields();

        $this->assertNull($fields->dataFieldByName('MenuItems'));
        $this->assertNotNull($fields->fieldByName('MenuPermissionMessage'));
    }
}
