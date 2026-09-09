<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuAdmin;
use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\Form;
use SilverStripe\Versioned\Versioned;
use SilverStripe\VersionedAdmin\Forms\HistoryViewerField;

/**
 * Menus are versioned: editing changes draft, and publishing sends the whole menu live.
 */
class MenuVersioningTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInWithPermission(['ADMIN']);
    }

    private function admin(array $vars = []): MenuAdmin
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

    private function header(): MenuSet
    {
        return $this->objFromFixture(MenuSet::class, 'header');
    }

    private function publishHeader(): MenuSet
    {
        $set = $this->header();
        $set->publishRecursive();

        return MenuSet::get()->byID($set->ID);
    }

    public function testMenusAreVersioned(): void
    {
        $this->assertTrue(MenuSet::singleton()->hasExtension(Versioned::class));
        $this->assertTrue(MenuItem::singleton()->hasExtension(Versioned::class));
    }

    public function testANewMenuStartsAsADraft(): void
    {
        $set = $this->header();

        $this->assertFalse($set->isPublished());
        $this->assertTrue($this->admin()->menuIsModified($set));
    }

    public function testPublishingSendsTheWholeMenuLive(): void
    {
        $set = $this->publishHeader();

        $this->assertTrue($set->isPublished());
        $this->assertFalse($this->admin()->menuIsModified($set));

        $liveItems = Versioned::get_by_stage(MenuItem::class, Versioned::LIVE)
            ->filter('MenuSetID', $set->ID);

        $this->assertCount(3, $liveItems, 'The links are published with the menu that owns them');
    }

    public function testAddingALinkIsADraftChange(): void
    {
        $set = $this->publishHeader();

        $source = MenuItemTreeSource::create()->setScopeID($set->ID);
        $created = $source->createNode(null);

        $this->assertFalse($created->isPublished());
        $this->assertTrue(
            $this->admin()->menuIsModified($set),
            'A new link leaves the menu with changes to publish'
        );

        $live = Versioned::get_by_stage(MenuItem::class, Versioned::LIVE)
            ->filter('MenuSetID', $set->ID);

        $this->assertCount(3, $live, 'The new link is not live until the menu is published');
    }

    public function testMovingALinkIsADraftChange(): void
    {
        $set = $this->publishHeader();

        $source = MenuItemTreeSource::create()->setScopeID($set->ID);
        $second = $source->getNode((string) $this->objFromFixture(MenuItem::class, 'header-2')->ID);

        $source->moveNode($second, null, 0);

        $this->assertTrue($this->admin()->menuIsModified($set));
        $this->assertTrue(MenuItem::get()->byID($second->ID)->isModifiedOnDraft());
    }

    public function testTheTreeReportsDraftAndModifiedState(): void
    {
        $set = $this->publishHeader();
        $source = MenuItemTreeSource::create()->setScopeID($set->ID);

        $published = $source->getNode((string) $this->objFromFixture(MenuItem::class, 'header-1')->ID);
        $this->assertSame('published', $source->getNodeData($published)['status']);

        $new = $source->createNode(null);
        $this->assertSame('draft', $source->getNodeData($new)['status']);

        $published->MenuTitle = 'Changed';
        $published->write();

        $refreshed = MenuItemTreeSource::create()->setScopeID($set->ID);
        $this->assertSame(
            'modified',
            $refreshed->getNodeData(MenuItem::get()->byID($published->ID))['status']
        );
    }

    public function testDraftStateShowsAsABadge(): void
    {
        $set = $this->publishHeader();
        $source = MenuItemTreeSource::create()->setScopeID($set->ID);
        $new = $source->createNode(null);

        $badges = array_column($source->getNodeData($new)['badges'], 'text');

        $this->assertContains('Draft', $badges);
    }

    public function testPublishActionPublishesTheMenu(): void
    {
        $set = $this->header();
        $admin = $this->admin(['MenuSetID' => (string) $set->ID]);

        $admin->publish([], Form::create($admin, 'EditForm'));

        $this->assertTrue(MenuSet::get()->byID($set->ID)->isPublished());
    }

    public function testUnpublishActionTakesTheMenuOffLive(): void
    {
        $set = $this->publishHeader();
        $admin = $this->admin(['MenuSetID' => (string) $set->ID]);

        $admin->unpublish([], Form::create($admin, 'EditForm'));

        $this->assertFalse(MenuSet::get()->byID($set->ID)->isPublished());
    }

    public function testAddingAMenuCreatesADraftWithAUniqueName(): void
    {
        $admin = $this->admin();
        $before = MenuSet::get()->count();

        $admin->addMenuSet([], Form::create($admin, 'EditForm'));
        $admin->addMenuSet([], Form::create($admin, 'EditForm'));

        $this->assertSame($before + 2, MenuSet::get()->count());

        $names = MenuSet::get()->column('Name');
        $this->assertSame(count($names), count(array_unique($names)));
    }

    public function testDeletingAPublishedMenuArchivesIt(): void
    {
        $set = $this->publishHeader();
        $admin = $this->admin(['MenuSetID' => (string) $set->ID]);

        $admin->deleteMenuSet([], Form::create($admin, 'EditForm'));

        $this->assertNull(MenuSet::get()->byID($set->ID));
        $this->assertCount(
            0,
            Versioned::get_by_stage(MenuSet::class, Versioned::LIVE)->filter('ID', $set->ID)
        );
    }

    public function testDeletingALinkTakesItOffLive(): void
    {
        $set = $this->publishHeader();
        $source = MenuItemTreeSource::create()->setScopeID($set->ID);
        $item = $this->objFromFixture(MenuItem::class, 'header-2');

        $source->deleteNode($source->getNode((string) $item->ID));

        $this->assertNull(MenuItem::get()->byID($item->ID));
        $this->assertCount(
            0,
            Versioned::get_by_stage(MenuItem::class, Versioned::LIVE)->filter('ID', $item->ID)
        );
    }

    public function testRecordsThatPreDateVersioningCanBePublished(): void
    {
        $set = $this->header();

        // Simulate a row written before the extension was applied
        $set->ensureVersionExists();
        $this->assertGreaterThan(0, (int) $set->getField('Version'));

        $this->assertFalse(
            $set->ensureVersionExists(),
            'A record that already has a version is left alone'
        );
    }

    public function testHistoryIsAvailableForMenusAndLinks(): void
    {
        $setFields = $this->header()->getCMSFields();
        $itemFields = $this->objFromFixture(MenuItem::class, 'header-1')->getCMSFields();

        $this->assertInstanceOf(
            HistoryViewerField::class,
            $setFields->dataFieldByName('MenuSetHistory')
        );
        $this->assertInstanceOf(
            HistoryViewerField::class,
            $itemFields->dataFieldByName('MenuItemHistory')
        );
    }

    public function testUnsavedRecordsHaveNoHistoryTab(): void
    {
        $this->assertNull(MenuSet::create()->getCMSFields()->dataFieldByName('MenuSetHistory'));
        $this->assertNull(MenuItem::create()->getCMSFields()->dataFieldByName('MenuItemHistory'));
    }

    public function testTheMenuPickerMarksDrafts(): void
    {
        $draft = $this->header();
        $this->assertStringContainsString('draft', $draft->getMenuAdminTitle());

        $published = $this->publishHeader();
        $this->assertStringNotContainsString('draft', $published->getMenuAdminTitle());
    }
}
