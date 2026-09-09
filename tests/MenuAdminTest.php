<?php

namespace Heyday\MenuManager\Test;

use Akqa\SilverStripe\TreeField\Form\TreeField;
use Heyday\MenuManager\MenuAdmin;
use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

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

        $this->logInWithPermission(['CMS_ACCESS', 'MANAGE_MENU_SETS', 'MANAGE_MENU_ITEMS']);
    }

    public function testTheSectionOpensOnTheListOfMenus(): void
    {
        $admin = $this->makeAdmin();

        $this->assertNull($admin->getCurrentMenuSet());

        $fields = $admin->getEditForm()->Fields();

        $this->assertNotNull($fields->fieldByName('Menus'));
        $this->assertNull($fields->dataFieldByName('MenuItems'));
    }

    public function testTheListShowsEveryMenu(): void
    {
        $html = (string) $this->makeAdmin()->getEditForm()->Fields()->fieldByName('Menus')->getContent();

        $this->assertStringContainsString('menu-admin__grid', $html);
        $this->assertSame(2, substr_count($html, 'menu-tile__title'));
        // The fixture names lose their spaces, and the title falls back to the name
        $this->assertStringContainsString('Header', $html);
        $this->assertStringContainsString('Footer1', $html);
    }

    public function testATileLinksToItsMenu(): void
    {
        $footer = $this->objFromFixture(MenuSet::class, 'footer');
        $html = (string) $this->makeAdmin()->getEditForm()->Fields()->fieldByName('Menus')->getContent();

        $this->assertStringContainsString('MenuSetID=' . $footer->ID, $html);
    }

    public function testATileShowsTheTitleRatherThanTheName(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'header');
        $set->Title = 'Main navigation';
        $set->write();

        $html = (string) $this->makeAdmin()->getEditForm()->Fields()->fieldByName('Menus')->getContent();

        $this->assertStringContainsString('Main navigation', $html);
        $this->assertStringContainsString('Header', $html, 'The reference name is shown too');
    }

    public function testATileShowsTheNumberOfLinksAndTheDescription(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'header');
        $set->Description = 'The main site navigation';
        $set->write();

        $html = (string) $this->makeAdmin()->getEditForm()->Fields()->fieldByName('Menus')->getContent();

        $this->assertStringContainsString('3 links', $html);
        $this->assertStringContainsString('The main site navigation', $html);
    }

    public function testATileFlagsUnpublishedChanges(): void
    {
        $html = (string) $this->makeAdmin()->getEditForm()->Fields()->fieldByName('Menus')->getContent();

        $this->assertStringContainsString('menu-tile--draft', $html, 'Unpublished menus are flagged');

        foreach (MenuSet::get() as $set) {
            $set->publishRecursive();
        }

        $html = (string) $this->makeAdmin()->getEditForm()->Fields()->fieldByName('Menus')->getContent();

        $this->assertStringNotContainsString('menu-tile--draft', $html);
    }

    public function testOpeningAMenuShowsItsTree(): void
    {
        $footer = $this->objFromFixture(MenuSet::class, 'footer');
        $admin = $this->makeAdmin(['MenuSetID' => (string) $footer->ID]);

        $this->assertSame($footer->ID, $admin->getCurrentMenuSet()->ID);

        $tree = $admin->getEditForm()->Fields()->dataFieldByName('MenuItems');

        $this->assertInstanceOf(TreeField::class, $tree);
        $this->assertSame(MenuItemTreeSource::KEY, $tree->getSourceKey());
        $this->assertSame($footer->ID, (int) $tree->getScopeID());
    }

    public function testOpeningAMenuOffersAWayBack(): void
    {
        $footer = $this->objFromFixture(MenuSet::class, 'footer');
        $fields = $this->makeAdmin(['MenuSetID' => (string) $footer->ID])->getEditForm()->Fields();

        $this->assertNotNull($fields->fieldByName('BackToMenus'));
    }

    public function testAnUnknownMenuInTheRequestFallsBackToTheList(): void
    {
        $admin = $this->makeAdmin(['MenuSetID' => '999999']);

        $this->assertNull($admin->getCurrentMenuSet());
        $this->assertNotNull($admin->getEditForm()->Fields()->fieldByName('Menus'));
    }

    public function testWithoutPermissionThereIsNoList(): void
    {
        $this->logInWithPermission(['CMS_ACCESS']);

        $html = (string) $this->makeAdmin()->getEditForm()->Fields()->fieldByName('Menus')->getContent();

        $this->assertStringNotContainsString('menu-tile', $html);
    }
}
