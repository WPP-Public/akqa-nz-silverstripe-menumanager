<?php

namespace Heyday\MenuManager\Test;

use Akqa\SilverStripe\TreeField\Controllers\TreeFieldController;
use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Versioned\Versioned;

/**
 * Saving in the Menus section: a link's own fields from the panel beside the tree, and the menu
 * itself from the section's toolbar.
 */
class MenuItemSaveTest extends FunctionalTest
{
    protected static $fixture_file = 'MenuTest.yml';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInWithPermission(['ADMIN']);
    }

    /**
     * Where the panel beside the tree posts a link's form, as the form's own action gives it.
     */
    private function nodeFormUrl(MenuItem $item): string
    {
        return 'admin/tree-field/nodeForm/' . MenuItemTreeSource::KEY . '/'
            . $item->MenuSetID . '/' . $item->ID;
    }

    private function saveLink(MenuItem $item, array $data): \SilverStripe\Control\HTTPResponse
    {
        return $this->post(
            $this->nodeFormUrl($item),
            $data + ['action_save' => 1],
            ['X-Formschema-Request' => 'schema,state,errors']
        );
    }

    private function draftOf(MenuItem $item): MenuItem
    {
        return Versioned::get_by_stage(MenuItem::class, Versioned::DRAFT)->byID($item->ID);
    }

    public function testSavingALinkKeepsItsLabelAnchorAndNewTab(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'header-2');

        $response = $this->saveLink($item, [
            'LinkType' => 'external',
            'MenuTitle' => 'Contact us',
            'Link' => 'https://example.com/contact',
            'Anchor' => 'form',
            'IsNewWindow' => 1,
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $saved = $this->draftOf($item);
        $this->assertSame('Contact us', $saved->MenuTitle);
        $this->assertSame('form', $saved->Anchor);
        $this->assertSame(1, (int) $saved->IsNewWindow);
        $this->assertSame('https://example.com/contact', $saved->getField('Link'));
    }

    public function testSavingALinkToAPageKeepsItsLabelAnchorAndNewTab(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'header-1');

        $response = $this->saveLink($item, [
            'LinkType' => 'internal',
            'PageID' => $item->PageID,
            'MenuTitle' => 'Start here',
            'Anchor' => 'intro',
            'IsNewWindow' => 1,
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $saved = $this->draftOf($item);
        $this->assertSame('Start here', $saved->MenuTitle);
        $this->assertSame('intro', $saved->Anchor);
        $this->assertSame(1, (int) $saved->IsNewWindow);
        $this->assertSame((int) $item->PageID, (int) $saved->PageID);
    }

    /**
     * A blank label follows the page's title. Loading the form must not fill it in with that
     * title, or saving anything else would fix the label to it.
     */
    public function testABlankLabelStaysBlankWhenSomethingElseIsSaved(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'header-1');
        $item->MenuTitle = null;
        $item->write();

        $this->assertSame('Home', $item->MenuTitle, 'A blank label falls back to the page title');

        $form = MenuItemTreeSource::create()
            ->setScopeID($item->MenuSetID)
            ->getNodeForm($item, 'Form', TreeFieldController::singleton());

        $this->assertSame('', (string) $form->Fields()->dataFieldByName('MenuTitle')->getValue());
        $this->assertSame(
            'Home',
            $form->Fields()->dataFieldByName('MenuTitle')->getAttribute('placeholder')
        );

        // What the panel posts back: the blank label it loaded, and the change
        $this->saveLink($item, [
            'LinkType' => 'internal',
            'PageID' => $item->PageID,
            'MenuTitle' => (string) $form->Fields()->dataFieldByName('MenuTitle')->getValue(),
            'Anchor' => 'intro',
        ]);

        $saved = $this->draftOf($item);
        $this->assertSame('', (string) $saved->getField('MenuTitle'));
        $this->assertSame('intro', $saved->Anchor);
        $this->assertSame('Home', $saved->MenuTitle);
    }

    public function testUntickingNewTabIsSaved(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'header-3');
        $item->IsNewWindow = true;
        $item->write();

        // An unticked checkbox is not posted at all
        $this->saveLink($item, [
            'LinkType' => 'external',
            'MenuTitle' => 'Header 3',
            'Link' => '#',
        ]);

        $this->assertSame(0, (int) $this->draftOf($item)->IsNewWindow);
    }

    /**
     * The panel updates the tree from the response rather than reloading the section, so the
     * response has to carry the saved tree.
     */
    public function testSavingALinkAnswersWithTheUpdatedTree(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'header-2');

        $response = $this->saveLink($item, [
            'LinkType' => 'external',
            'MenuTitle' => 'Renamed',
            'Link' => '#',
        ]);

        $this->assertStringContainsString('application/json', $response->getHeader('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true);
        $titles = array_column($payload['tree']['nodes'] ?? [], 'title');

        $this->assertContains('Renamed', $titles);
    }

    /**
     * Saving the menu from the toolbar re-renders the section, which must open the same link again.
     */
    public function testSavingTheMenuKeepsTheOpenLinkOpen(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'header');
        $item = $this->objFromFixture(MenuItem::class, 'header-2');

        $this->get('admin/menu-manager?MenuSetID=' . $set->ID . '&MenuItemID=' . $item->ID);

        $response = $this->post('admin/menu-manager/EditForm', [
            'MenuSetID' => $set->ID,
            'MenuItemID' => $item->ID,
            'Title' => 'Header menu',
            'Name' => $set->Name,
            'action_save' => 1,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-selected-id="' . $item->ID . '"', (string) $response->getBody());
        $this->assertSame('Header menu', MenuSet::get()->byID($set->ID)->Title);
    }

    public function testTheMenuFormCarriesTheOpenLink(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'header');
        $item = $this->objFromFixture(MenuItem::class, 'header-2');

        $body = (string) $this->get(
            'admin/menu-manager?MenuSetID=' . $set->ID . '&MenuItemID=' . $item->ID
        )->getBody();

        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="MenuItemID" value="' . $item->ID . '"/',
            $body
        );
    }

    /**
     * One wording, in sentence case, wherever a link can be added.
     */
    public function testAddingALinkReadsTheSameEverywhere(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'header');
        $labels = MenuItemTreeSource::create()->setScopeID($set->ID)->getLabels();

        $this->assertSame('Add menu item', $labels['addRoot']);
        $this->assertSame('Add menu item', $labels['addChild']);
        $this->assertSame('New menu item', $labels['newTitle']);
        $this->assertSame('Untitled menu item', $labels['untitled']);
    }
}
