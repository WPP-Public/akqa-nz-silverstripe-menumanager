<?php

namespace Heyday\MenuManager;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextareaField;
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
    private static $table_name = 'MenuSet';

    private static $db = [
        'Name' => 'Varchar(255)',
        'Description' => 'Text',
        'Sort' => 'Int'
    ];

    private static $has_many = [
        'MenuItems' => MenuItem::class,
    ];

    private static $cascade_deletes = [
        'MenuItems'
    ];

    private static $searchable_fields = [
        'Name',
        'Description'
    ];

    private static $default_sort = 'Sort ASC';

    /**
     * @return array
     */
    public function providePermissions()
    {
        return [
            'MANAGE_MENU_SETS' => _t(__CLASS__ . '.ManageMenuSets', 'Manage Menu Sets'),
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
         * @deprecated Since 4.0
         * Use an index for the Name field instead https://docs.silverstripe.org/en/4/developer_guides/model/indexes/
         */
        if ($existing && $existing->ID !== $this->ID) {
            // MenuSets must have a unique Name
            $result->addError(
                _t(
                    __CLASS__ . 'AlreadyExists',
                    'A Menu Set with the Name "{name}" already exists',
                    ['name' => $this->Name]
                ),
                ValidationResult::TYPE_ERROR
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
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_SETS');
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        // Backwards compatibility for duplicate default sets
        $existing = MenuManagerTemplateProvider::MenuSet($this->Name);
        $isDuplicate = $existing && $existing->ID !== $this->ID;

        if ($this->isDefaultSet() && !$isDuplicate) {
            return false;
        }

        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_SETS');
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canEdit($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return (Permission::check('MANAGE_MENU_SETS') || Permission::check('MANAGE_MENU_ITEMS'));
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }


        return (Permission::check('MANAGE_MENU_SETS') || Permission::check('MANAGE_MENU_ITEMS'));
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
                $set = MenuSet::create();
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
                    '',
                    $this->MenuItems(),
                    $config = GridFieldConfig_RelationEditor::create()
                )
            );

            $config->addComponent(new GridFieldOrderableRows('Sort'));

            $fields->addFieldToTab(
                'Root.Meta',
                TextareaField::create('Description', _t(__CLASS__ . '.DB_Description', 'Description'))
            );
        } else {
            $fields->addFieldToTab(
                'Root.Main',
                TextField::create(
                    'Name',
                    _t(__CLASS__ . '.DB_Name', 'Name')
                )->setDescription(
                    _t(
                        __CLASS__ . '.DB_Name_Description',
                        'This field can\'t be changed once set'
                    )
                )
            );

            $fields->addFieldToTab(
                'Root.Main',
                TextareaField::create('Description', _t(__CLASS__ . '.DB_Description', 'Description'))
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


    /**
     * @return array
     */
    public function summaryFields()
    {
        return [
            'Name' => _t(__CLASS__ . '.DB_Name', 'Name'),
            'Description' => _t(__CLASS__ . '.DB_Description', 'Description'),
            'MenuItems.Count' => _t(__CLASS__ . '.DB_Items', 'Items')
        ];
    }
}
