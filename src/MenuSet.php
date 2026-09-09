<?php

namespace Heyday\MenuManager;

use Akqa\SilverStripe\TreeField\Form\TreeField;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class MenuSet
 */
class MenuSet extends DataObject implements PermissionProvider
{
    private static string $table_name = 'MenuSet';

    private static array $db = [
        'Name' => 'Varchar(255)',
        'Description' => 'Text',
        'Sort' => 'Int'
    ];

    private static array $has_many = [
        'MenuItems' => MenuItem::class,
    ];

    private static array $cascade_deletes = [
        'MenuItems'
    ];

    private static array $cascade_duplicates = [
        'MenuItems'
    ];

    private static array $searchable_fields = [
        'Name',
        'Description'
    ];

    private static string $default_sort = 'Sort ASC';

    /**
     * @return array
     */
    public function providePermissions(): array
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
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if ($this->Name === null || $this->Name === '') {
            return $result;
        }

        $existing = MenuManagerTemplateProvider::getMenuSet($this->Name);

        if ($existing && $existing->ID !== $this->ID && $existing->Name === $this->Name) {
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
    public function canCreate($member = null, $context = []): bool
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
    public function canDelete($member = null): bool
    {
        // Backwards compatibility for duplicate default sets
        $existing = MenuManagerTemplateProvider::getMenuSet($this->Name);
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
    public function canEdit($member = null): bool
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
    public function canView($member = null): bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }


        return (Permission::check('MANAGE_MENU_SETS') || Permission::check('MANAGE_MENU_ITEMS'));
    }


    /**
     * @return HasManyList<MenuItem>
     */
    public function getChildren(): HasManyList
    {
        return $this->MenuItems();
    }


    /**
     * The top level items of this set, for templates that render a nested menu.
     *
     * $MenuItems still returns every item in the set regardless of nesting, so templates that
     * want a hierarchy should loop this and then $Children on each item.
     *
     * @return HasManyList<MenuItem>
     */
    public function getRootMenuItems(): HasManyList
    {
        return $this->MenuItems()
            ->filter('ParentItemID', 0)
            ->sort(['Sort' => 'ASC', 'ID' => 'ASC']);
    }


    /**
     * Check if this menu set appears in the default sets config
     * @return bool
     */
    public function isDefaultSet(): bool
    {
        return in_array($this->Name, $this->getDefaultSetNames());
    }


    /**
     * Set up default records based on the yaml config
     */
    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        if ($this->createDefaultMenuSets()) {
            DB::alteration_message(sprintf(
                "MenuSets created (%s)",
                implode(', ', $this->getDefaultSetNames())
            ), 'created');
        }
    }


    public function createDefaultMenuSets()
    {
        if ($this->getDefaultSetNames()) {
            foreach ($this->getDefaultSetNames() as $name) {
                $existingRecord = MenuSet::get()
                    ->filter('Name', $name)
                    ->first();

                if (!$existingRecord) {
                    $set = MenuSet::create();
                    $set->Name = $name;
                    $set->write();
                }
            }

            return true;
        }

        return false;
    }


    /**
     * The field used to manage this set's items: a tree with drag and drop nesting, and the
     * selected item's own CMS fields alongside it.
     */
    protected function getMenuItemsField(): FormField
    {
        return TreeField::create(
            'MenuItems',
            _t(__CLASS__ . '.DB_Items', 'Items'),
            MenuItemTreeSource::KEY,
            (int) $this->ID
        );
    }


    /**
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $fields = FieldList::create(TabSet::create('Root'));
        if ($this->ID != null) {
            $fields->removeByName('Name');
            $fields->addFieldToTab('Root.Main', $this->getMenuItemsField());
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
    public function getDefaultSetNames()
    {
        return $this->config()->get('default_sets') ?: [];
    }


    /**
     * @return array
     */
    public function summaryFields(): array
    {
        return [
            'Name' => _t(__CLASS__ . '.DB_Name', 'Name'),
            'Description' => _t(__CLASS__ . '.DB_Description', 'Description'),
            'MenuItems.Count' => _t(__CLASS__ . '.DB_Items', 'Items')
        ];
    }


    public function asArray(): array
    {
        return [
            'name' => $this->Name,
            'description' => $this->Description,
            'items' => array_map(
                fn (MenuItem $item) => $item->asArray(),
                $this->getRootMenuItems()->toArray()
            ),
        ];
    }
}
