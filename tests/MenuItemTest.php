<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;

class MenuItemTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    public function testPermission(): void
    {
        $this->logInWithPermission('MANAGE_MENU_SETS');
        $item = $this->objFromFixture(MenuItem::class, 'header-1');
        $this->assertFalse($item->canCreate());
        $this->assertFalse($item->canEdit());
        $this->assertFalse($item->canView());
        $this->assertFalse($item->canDelete());

        $this->logInWithPermission('MANAGE_MENU_ITEMS');
        $this->assertTrue($item->canCreate());
        $this->assertTrue($item->canEdit());
        $this->assertTrue($item->canView());
        $this->assertTrue($item->canDelete());
    }

    public function testCmsFields(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'header-1');
        $fields = $item->getCMSFields();

        $this->assertInstanceOf(OptionsetField::class, $fields->dataFieldByName('LinkType'));
        $this->assertInstanceOf(TextField::class, $fields->dataFieldByName('MenuTitle'));
        $this->assertInstanceOf(TreeDropdownField::class, $fields->dataFieldByName('PageID'));
        $this->assertInstanceOf(TextField::class, $fields->dataFieldByName('Link'));
        $this->assertInstanceOf(TextField::class, $fields->dataFieldByName('Anchor'));
        $this->assertInstanceOf(CheckboxField::class, $fields->dataFieldByName('IsNewWindow'));
        $this->assertInstanceOf(UploadField::class, $fields->dataFieldByName('File'));
    }

    public function testGetAbsoluteURLForInternalPage(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'header-1');

        $this->assertSame($item->Page()->AbsoluteLink(), $item->getAbsoluteURL());
    }

    public function testGetAbsoluteURLForInternalPageWithAnchor(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'header-1');
        $item->Anchor = 'section-one';

        $this->assertSame(
            $item->Page()->AbsoluteLink() . '#section-one',
            $item->getAbsoluteURL()
        );
    }

    public function testGetAbsoluteURLForExternalLink(): void
    {
        $item = $this->objFromFixture(MenuItem::class, 'header-2');

        $this->assertSame($item->getURL(), $item->getAbsoluteURL());
    }

    public function testGetURLReturnsEmptyStringWhenNoDestinationIsSet(): void
    {
        $item = MenuItem::create();
        $item->MenuTitle = 'No destination';

        $this->assertSame('', $item->getURL());
        $this->assertSame('', $item->getAbsoluteURL());
        $this->assertNull($item->getTreeNodeSubtitle());
    }

    public function testSavingAsExternalClearsALeftoverPage(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'header');
        $item = MenuItem::create();
        $item->MenuSetID = $set->ID;
        $item->MenuTitle = 'External after page';
        $item->PageID = $this->objFromFixture(MenuItem::class, 'header-1')->PageID;
        $item->Link = 'https://example.com/switch';
        $item->LinkType = 'external';
        $item->write();

        $this->assertSame(0, (int) $item->PageID);
        $this->assertSame('https://example.com/switch', $item->getField('Link'));
        $this->assertSame('external', $item->getLinkType());
    }

    public function testSavingAsInternalClearsAnExternalUrl(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'header');
        $item = MenuItem::create();
        $item->MenuSetID = $set->ID;
        $item->MenuTitle = 'Internal after url';
        $item->PageID = $this->objFromFixture(MenuItem::class, 'header-1')->PageID;
        $item->Link = 'https://example.com/stale';
        $item->LinkType = 'internal';
        $item->write();

        $this->assertSame('', (string) $item->getField('Link'));
        $this->assertGreaterThan(0, (int) $item->PageID);
        $this->assertSame('internal', $item->getLinkType());
    }
}
