<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuAdmin;
use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
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

    private function table(string $class): string
    {
        return DataObject::getSchema()->baseDataTable($class);
    }

    private function setColumn(string $class, int $id, string $column, string $value): void
    {
        DB::prepared_query(
            sprintf('UPDATE "%s" SET "%s" = ? WHERE "ID" = ?', $this->table($class), $column),
            [$value, $id]
        );
    }

    private function readColumn(string $class, int $id, string $column): ?string
    {
        return DB::prepared_query(
            sprintf('SELECT "%s" FROM "%s" WHERE "ID" = ?', $column, $this->table($class)),
            [$id]
        )->value();
    }

    /**
     * Put the menu tables back the way they looked before the module was versioned: rows in the
     * base table with Version 0, no history, and nothing live.
     */
    private function demoteToPreVersioned(): void
    {
        foreach ([MenuSet::class, MenuItem::class] as $class) {
            $table = $this->table($class);

            DB::query(sprintf('UPDATE "%s" SET "Version" = 0', $table));
            DB::query(sprintf('DELETE FROM "%s_Versions"', $table));
            DB::query(sprintf('DELETE FROM "%s_Live"', $table));
        }
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

    public function testPublishingReachesNestedLinks(): void
    {
        $parent = $this->objFromFixture(MenuItem::class, 'header-1');
        $child = $this->objFromFixture(MenuItem::class, 'header-2');
        $child->ParentItemID = $parent->ID;
        $child->write();

        $grandchild = $this->objFromFixture(MenuItem::class, 'header-3');
        $grandchild->ParentItemID = $child->ID;
        $grandchild->write();

        $set = $this->publishHeader();

        $live = Versioned::get_by_stage(MenuItem::class, Versioned::LIVE)
            ->filter('MenuSetID', $set->ID);

        $this->assertCount(3, $live, 'Publishing a menu reaches links at every level');
        $this->assertFalse($set->hasDraftChanges());
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

    public function testDraftStateShowsAsAStatusDot(): void
    {
        $set = $this->publishHeader();
        $source = MenuItemTreeSource::create()->setScopeID($set->ID);
        $new = $source->createNode(null);

        $data = $source->getNodeData($new);
        $badgeTexts = array_column($data['badges'], 'text');

        $this->assertSame('draft', $data['status']);
        $this->assertNotContains('Draft', $badgeTexts);
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

    public function testADefaultSetCannotBeUnpublished(): void
    {
        Config::modify()->set(MenuSet::class, 'default_sets', ['Header']);
        $set = $this->publishHeader();

        // Not even ADMIN, which Versioned would otherwise always let through
        $this->assertFalse($set->canUnpublish());
        $this->assertFalse($set->canDelete());
        $this->assertNull($set->getCMSActions()->fieldByName('action_unpublish'));
        $this->assertNull($set->getCMSActions()->fieldByName('action_delete'));

        $admin = $this->admin(['MenuSetID' => (string) $set->ID]);

        try {
            $admin->unpublish([], Form::create($admin, 'EditForm'));
            $this->fail('Unpublishing a default set should be refused');
        } catch (\SilverStripe\Control\HTTPResponse_Exception $e) {
            $this->assertSame(403, $e->getResponse()->getStatusCode());
        }

        $this->assertTrue(MenuSet::get()->byID($set->ID)->isPublished());
    }

    public function testDeleteRefusesWithoutAMenuToDelete(): void
    {
        $admin = $this->admin();

        $this->expectException(\SilverStripe\Control\HTTPResponse_Exception::class);
        $admin->delete([], Form::create($admin, 'EditForm'));
    }

    public function testDeleteOnlyEverRemovesTheMenuItWasGiven(): void
    {
        $header = $this->header();
        $footer = $this->objFromFixture(MenuSet::class, 'footer');

        $admin = $this->admin(['MenuSetID' => (string) $header->ID]);
        $admin->delete(['ID' => $footer->ID], Form::create($admin, 'EditForm'));

        $this->assertNull(MenuSet::get()->byID($footer->ID));
        $this->assertNotNull(
            MenuSet::get()->byID($header->ID),
            'The menu that happened to be open is untouched'
        );
    }

    public function testAddingAMenuGivesItAUniqueName(): void
    {
        $before = MenuSet::get()->count();

        // A fresh controller each time: one request only ever redirects once
        foreach ([$this->admin(), $this->admin()] as $admin) {
            $admin->addMenuSet([], Form::create($admin, 'EditForm'));
        }

        $this->assertSame($before + 2, MenuSet::get()->count());

        $added = MenuSet::get()->sort('ID', 'DESC')->limit(2)->column('Name');

        $this->assertSame(['NewMenu2', 'NewMenu'], $added);
        $this->assertSame('New menu', MenuSet::get()->sort('ID', 'DESC')->first()->Title);
    }

    public function testTheNameStaysEditableUnlessTheMenuIsADefault(): void
    {
        $set = MenuSet::create();
        $set->Name = 'Sidebar Menu';
        $set->write();

        $set = MenuSet::get()->byID($set->ID);

        $this->assertSame('SidebarMenu', $set->Name, 'Spaces are removed from the reference');
        $this->assertFalse($set->getCMSFields()->dataFieldByName('Name')->isReadonly());

        $set->Name = 'Aside';
        $set->write();
        $this->assertSame('Aside', MenuSet::get()->byID($set->ID)->Name);

        Config::modify()->set(MenuSet::class, 'default_sets', ['Aside']);
        $this->assertTrue($set->getCMSFields()->dataFieldByName('Name')->isReadonly());
    }

    public function testDeletingAPublishedMenuArchivesIt(): void
    {
        $set = $this->publishHeader();
        $admin = $this->admin(['MenuSetID' => (string) $set->ID]);

        $admin->delete(['ID' => $set->ID], Form::create($admin, 'EditForm'));

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
        $id = $this->header()->ID;
        $this->demoteToPreVersioned();

        $set = MenuSet::get()->byID($id);
        $this->assertSame(0, (int) $set->getField('Version'), 'the row has no version to start with');
        $this->assertTrue($set->ensureVersionExists());
        $this->assertGreaterThan(0, (int) $set->getField('Version'));

        $this->assertFalse(
            $set->ensureVersionExists(),
            'A record that already has a version is left alone'
        );
    }

    public function testABuildPublishesMenusThatPreDateVersioning(): void
    {
        $id = $this->header()->ID;
        $this->demoteToPreVersioned();

        MenuSet::singleton()->requireDefaultRecords();

        $this->assertNotNull(
            Versioned::get_by_stage(MenuSet::class, Versioned::LIVE)->byID($id),
            'the build sends a menu that pre-dates versioning live'
        );
    }

    public function testABuildKeepsTheNameOfAMenuThatPreDatesVersioning(): void
    {
        $id = $this->header()->ID;
        $this->demoteToPreVersioned();

        // A name the module accepted before it reshaped names on save
        $this->setColumn(MenuSet::class, $id, 'Name', 'Site Header');

        MenuSet::singleton()->requireDefaultRecords();

        $this->assertSame(
            'Site Header',
            $this->readColumn(MenuSet::class, $id, 'Name'),
            'a template finds a menu by its exact name, so the build must not rename it'
        );
    }

    public function testABuildKeepsTheLinkOfALinkThatPreDatesVersioning(): void
    {
        // header-1 points at a page. The old form let an editor keep a URL on it as well.
        $id = $this->objFromFixture(MenuItem::class, 'header-1')->ID;
        $this->demoteToPreVersioned();
        $this->setColumn(MenuItem::class, $id, 'Link', 'https://example.com/legacy');

        MenuSet::singleton()->requireDefaultRecords();

        $this->assertSame(
            'https://example.com/legacy',
            $this->readColumn(MenuItem::class, $id, 'Link'),
            'the build must not clear a link stored alongside a page'
        );
    }

    public function testABuildHandlesNamesThatOnlyDifferByASpace(): void
    {
        $header = $this->header()->ID;
        $footer = $this->objFromFixture(MenuSet::class, 'footer')->ID;
        $this->demoteToPreVersioned();

        // Reshaping either name would collide with the other and fail validation mid-build
        $this->setColumn(MenuSet::class, $header, 'Name', 'Footer 1');
        $this->setColumn(MenuSet::class, $footer, 'Name', 'Footer1');

        MenuSet::singleton()->requireDefaultRecords();

        $live = Versioned::get_by_stage(MenuSet::class, Versioned::LIVE);
        $this->assertNotNull($live->byID($header), 'the build completed and published both menus');
        $this->assertNotNull($live->byID($footer));
    }

    public function testABuildHandlesALegacyNameThatMatchesADefaultSet(): void
    {
        Config::modify()->set(MenuSet::class, 'default_sets', ['Aside']);
        $id = $this->header()->ID;
        $this->demoteToPreVersioned();

        // Reshaping this name would match the default set the same build has just created
        $this->setColumn(MenuSet::class, $id, 'Name', 'A side');

        MenuSet::singleton()->requireDefaultRecords();

        $this->assertNotNull(
            Versioned::get_by_stage(MenuSet::class, Versioned::LIVE)->byID($id),
            'the build completed and published the menu it found'
        );
        $this->assertSame('A side', $this->readColumn(MenuSet::class, $id, 'Name'));
    }

    public function testRenamingAMenuStillReshapesTheName(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'footer');
        $set->Name = 'Site Footer';
        $set->write();

        $this->assertSame('SiteFooter', MenuSet::get()->byID($set->ID)->Name);
    }

    public function testHistoryIsAvailableForAMenu(): void
    {
        $fields = $this->header()->getCMSFields();

        $this->assertInstanceOf(
            HistoryViewerField::class,
            $fields->dataFieldByName('MenuSetHistory')
        );
    }

    /**
     * A link is edited in a narrow panel beside the tree, which is no place for a history viewer.
     * The menu's own History tab covers its links, since publishing is per menu.
     */
    public function testALinkHasNoHistoryTab(): void
    {
        $fields = $this->objFromFixture(MenuItem::class, 'header-1')->getCMSFields();

        $this->assertNull($fields->dataFieldByName('MenuItemHistory'));
        $this->assertNull($fields->fieldByName('Root.History'));
    }

    public function testAnUnsavedMenuHasNoHistoryTab(): void
    {
        $this->assertNull(MenuSet::create()->getCMSFields()->dataFieldByName('MenuSetHistory'));
    }

    public function testTheMenuPickerMarksDrafts(): void
    {
        $draft = $this->header();
        $this->assertStringContainsString('draft', $draft->getMenuAdminTitle());

        $published = $this->publishHeader();
        $this->assertStringNotContainsString('draft', $published->getMenuAdminTitle());
    }
}
