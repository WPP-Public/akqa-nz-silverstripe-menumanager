<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuAdmin;
use Heyday\MenuManager\MenuSet;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\GridField\GridField;

class MenuAdminTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    public function testEditForm(): void
    {
        $menuSetName = str_replace('\\', '-', MenuSet::class);
        $admin = Injector::inst()->get(MenuAdmin::class);
        $request = Injector::inst()->get(HTTPRequest::class, true, ['GET', '']);
        $request->setSession(new Session([]));
        $request->setRouteParams(['ModelClass' => $menuSetName]);
        $admin->setRequest($request);
        $admin->doInit();

        $form = $admin->getEditForm()->Fields();
        $this->assertInstanceOf(GridField::class, $form->dataFieldByName($menuSetName));
    }
}
