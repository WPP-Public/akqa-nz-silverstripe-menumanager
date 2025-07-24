<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\MenuManagerTemplateProvider;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\GridField\GridField;
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

        $this->assertInstanceOf(GridField::class, $fields->dataFieldByName('MenuItems'));
        $this->assertNull($fields->dataFieldByName('Name'));

        $this->assertInstanceOf(
            TextField::class,
            MenuSet::create()->getCMSFields()->dataFieldByName('Name')
        );
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
