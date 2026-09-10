<?php

namespace Heyday\MenuManager\Test;

use Akqa\SilverStripe\TreeField\Form\TreeField;
use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\MenuManagerTemplateProvider;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\TextField;
use SilverStripe\Core\Validation\ValidationResult;

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

        Config::modify()->set(MenuSet::class, 'default_sets', ['Header']);
        $this->assertFalse($menu->canDelete());
    }

    public function testCmsFields(): void
    {
        $menu = $this->objFromFixture(MenuSet::class, 'header');
        $fields = $menu->getCMSFields();

        $this->assertInstanceOf(TreeField::class, $fields->dataFieldByName('MenuItems'));

        // The name is the reference templates use, so it is readonly once the menu exists
        $name = $fields->dataFieldByName('Name');
        $this->assertNotNull($name);
        $this->assertTrue($name->isReadonly());
        $this->assertNotEmpty($name->getDescription());

        // The editor facing title stays editable
        $this->assertInstanceOf(TextField::class, $fields->dataFieldByName('Title'));
        $this->assertFalse($fields->dataFieldByName('Title')->isReadonly());

        $this->assertInstanceOf(
            TextField::class,
            MenuSet::create()->getCMSFields()->dataFieldByName('Name')
        );
    }

    public function testNameHasSpacesRemoved(): void
    {
        $set = MenuSet::create();
        $set->Name = 'Main Menu With Spaces';
        $set->write();

        $this->assertSame('MainMenuWithSpaces', MenuSet::get()->byID($set->ID)->Name);
    }

    public function testTitleIsSeparateFromNameAndEditable(): void
    {
        $set = MenuSet::create();
        $set->Name = 'FooterMenu9';
        $set->Title = 'Footer, small print';
        $set->write();

        $set = MenuSet::get()->byID($set->ID);

        $this->assertSame('FooterMenu9', $set->Name);
        $this->assertSame('Footer, small print', $set->Title);
        $this->assertSame('Footer, small print', $set->getTitle());
    }

    public function testTitleFallsBackToTheName(): void
    {
        $set = MenuSet::create();
        $set->Name = 'NoTitleHere';
        $set->write();

        $this->assertSame('NoTitleHere', MenuSet::get()->byID($set->ID)->getTitle());
    }

    public function testADefaultMenuCannotBeRenamed(): void
    {
        Config::modify()->set(MenuSet::class, 'default_sets', ['Header']);

        $set = $this->objFromFixture(MenuSet::class, 'header');
        $set->Name = 'SomethingElse';
        $result = $set->validate();

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('cannot be changed', $result->getMessages()[0]['message']);
    }

    public function testADefaultMenuCanStillBeRetitled(): void
    {
        Config::modify()->set(MenuSet::class, 'default_sets', ['Header']);

        $set = $this->objFromFixture(MenuSet::class, 'header');
        $set->Title = 'Top navigation';

        $this->assertTrue($set->validate()->isValid());
    }

    public function testANonDefaultMenuCanBeRenamed(): void
    {
        $set = $this->objFromFixture(MenuSet::class, 'footer');
        $set->Name = 'Renamed';

        $this->assertTrue($set->validate()->isValid());
    }

    public function testValidateWithUniqueName(): void
    {
        $menuSet = MenuSet::create();
        $menuSet->Name = 'UniqueMenuSet';

        $result = $menuSet->validate();

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getMessages());
    }

    public function testValidateWithDuplicateName(): void
    {
        // Create a MenuSet with a specific name
        $existingMenuSet = MenuSet::create();
        $existingMenuSet->Name = 'DuplicateMenuSet';
        $existingMenuSet->write();

        // Try to create another MenuSet with the same name
        $newMenuSet = MenuSet::create();
        $newMenuSet->Name = 'DuplicateMenuSet';

        $result = $newMenuSet->validate();

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getMessages());

        // Check that the error message contains the expected text
        $messages = $result->getMessages();
        $errorMessage = '';
        foreach ($messages as $message) {
            if (isset($message['message'])) {
                $errorMessage = $message['message'];
                break;
            }
        }

        $this->assertStringContainsString('DuplicateMenuSet', $errorMessage);
    }

    public function testValidateWithSameNameSameId(): void
    {
        // Create a MenuSet
        $menuSet = MenuSet::create();
        $menuSet->Name = 'TestMenuSet';
        $menuSet->write();

        // Update the same MenuSet (should be valid)
        $result = $menuSet->validate();

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getMessages());
    }

    public function testValidateWithEmptyName(): void
    {
        $menuSet = MenuSet::create();
        $menuSet->Name = '';

        $result = $menuSet->validate();

        // Should still be valid as the validation only checks for duplicates
        $this->assertTrue($result->isValid());
    }

    public function testValidateWithNullName(): void
    {
        $menuSet = MenuSet::create();
        $menuSet->Name = null;

        $result = $menuSet->validate();

        // Should still be valid as the validation only checks for duplicates
        $this->assertTrue($result->isValid());
    }

    public function testValidateWithCaseSensitiveNames(): void
    {
        // Create a MenuSet with lowercase name
        $existingMenuSet = MenuSet::create();
        $existingMenuSet->Name = 'testmenuset';
        $existingMenuSet->write();

        // Try to create another MenuSet with uppercase name
        $newMenuSet = MenuSet::create();
        $newMenuSet->Name = 'TESTMENUSET';

        $result = $newMenuSet->validate();

        // Should be valid as the comparison is case-sensitive
        $this->assertTrue($result->isValid());
    }

    /**
     * Test validation logic without database operations
     */
    public function testValidateLogic(): void
    {
        // Test that validation returns a ValidationResult object
        $menuSet = MenuSet::create();
        $menuSet->Name = 'TestMenuSet';

        $result = $menuSet->validate();

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
    }
}
