<?php

namespace Heyday\MenuManager\Test;

use Akqa\SilverStripe\TreeField\Form\TreeField;
use Heyday\MenuManager\MenuAdmin;
use Heyday\MenuManager\TreeField\MenuTreeSource;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class MenuAdminTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    private function makeAdmin(): MenuAdmin
    {
        $admin = Injector::inst()->get(MenuAdmin::class);
        $request = Injector::inst()->get(HTTPRequest::class, true, ['GET', '']);
        $request->setSession(new Session([]));
        $admin->setRequest($request);
        $admin->doInit();

        return $admin;
    }

    public function testEditFormShowsTheMenuTree(): void
    {
        $this->logInWithPermission(['CMS_ACCESS', 'MANAGE_MENU_SETS', 'MANAGE_MENU_ITEMS']);

        $field = $this->makeAdmin()->getEditForm()->Fields()->dataFieldByName('Menus');

        $this->assertInstanceOf(TreeField::class, $field);
        $this->assertSame(MenuTreeSource::KEY, $field->getSourceKey());
    }

    public function testEditFormExplainsItselfWithoutPermission(): void
    {
        $this->logInWithPermission(['CMS_ACCESS']);

        $fields = $this->makeAdmin()->getEditForm()->Fields();

        $this->assertNull($fields->dataFieldByName('Menus'));
        $this->assertNotNull($fields->fieldByName('MenuPermissionMessage'));
    }
}
