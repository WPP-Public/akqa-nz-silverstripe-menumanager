<?php

namespace Heyday\MenuManager;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class MenuSet
 */
class MenuSet extends DataObject implements PermissionProvider
{
    /**
     * @var string
     */
    private static $table_name = 'MenuSet';

    /**
     * @var array
     */
    private static $db = [
        'Name' => 'Varchar(255)'
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'MenuItems' => 'Heyday\MenuManager\MenuItem'
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'Name'
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Name'
    ];

    /**
     * @return array
     */
    public function providePermissions()
    {
        return [
            'MANAGE_MENU_SETS' => 'Manage Menu Sets',
        ];
    }

    /**
     * Check for existing MenuSets with the same name
     *
     * {@inheritDoc}
     */
    public function validate()
    {
        $result = parent::validate();

        $existing = MenuManagerTemplateProvider::MenuSet($this->Name);

        /**
         * @deprecated Since 4.0 Use an index for the Name field instead https://docs.silverstripe.org/en/4/developer_guides/model/indexes/
         */
        if ($existing && $existing->ID !== $this->ID) {
            // MenuSets must have a unique Name
            $result->addError(
                sprintf('A "%s" with the Name "%s" already exists', static::class, $this->Name),
                ValidationResult::TYPE_ERROR,
            );
        }

        return $result;
    }

    /**
     * @param mixed $member
     * @param array $context
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        if (Permission::check('MANAGE_MENU_SETS')) {
            return true;
        }

        return parent::canCreate($member, $context);
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        $canDelete = parent::canDelete($member);

        // Backwards compatibility for duplicate default sets
        $existing = MenuManagerTemplateProvider::MenuSet($this->Name);
        $isDuplicate = $existing && $existing->ID !== $this->ID;

        if ($this->isDefaultSet() && !$isDuplicate) {
            // Default menu's cannot be deleted
            $canDelete = false;
        }

        if ($canDelete !== null) {
            return $canDelete;
        }

        return Permission::check('MANAGE_MENU_SETS');
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canEdit($member = null)
    {
        if (Permission::check('MANAGE_MENU_SETS') || Permission::check('MANAGE_MENU_ITEMS')) {
            return true;
        }

        return parent::canEdit($member);
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canView($member = null)
    {
        if (Permission::check('MANAGE_MENU_SETS') || Permission::check('MANAGE_MENU_ITEMS')) {
            return true;
        }

        return parent::canView($member);
    }

    /**
     * @return mixed
     */
    public function Children()
    {
        return $this->MenuItems();
    }

    /**
     * Check if this menu set appears in the default sets config
     * @return bool
     */
    public function isDefaultSet()
    {
        return in_array($this->Name, $this->getDefaultSetNames());
    }

    /**
     * Set up default records based on the yaml config
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        foreach ($this->getDefaultSetNames() as $name) {
            $existingRecord = MenuSet::get()
                ->filter('Name', $name)
                ->first();

            if (!$existingRecord) {
                $set = new MenuSet();
                $set->Name = $name;
                $set->write();

                DB::alteration_message("MenuSet '$name' created", 'created');
            }
        }
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = FieldList::create(TabSet::create('Root'));

        if ($this->ID != null) {
            $fields->removeByName('Name');

            $fields->addFieldToTab(
                'Root.Main',
                new GridField(
                    'MenuItems',
                    'Menu Items',
                    $this->MenuItems(),
                    $config = GridFieldConfig_RelationEditor::create()
                )
            );

            $config->addComponent(new GridFieldOrderableRows('Sort'));

        } else {
            $fields->addFieldToTab('Root.Main',
                new TextField('Name', 'Name (this field can\'t be changed once set)')
            );
        }

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * {@inheritDoc}
     */
    public function onBeforeDelete()
    {
        $menuItems = $this->MenuItems();

        if ($menuItems instanceof DataList && count($menuItems) > 0) {
            foreach ($menuItems as $menuItem) {
                $menuItem->delete();
            }
        }

        parent::onBeforeDelete();
    }

    /**
     * Get the MenuSet names configured under MenuSet.default_sets
     *
     * @return string[]
     */
    protected function getDefaultSetNames()
    {
        return $this->config()->get('default_sets') ?: [];
    }
}
