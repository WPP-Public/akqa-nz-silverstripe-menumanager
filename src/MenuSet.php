<?php

namespace Heyday\MenuManager;

use Akqa\SilverStripe\TreeField\Form\TreeField;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\ORM\CMSPreviewable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Versioned\Versioned;
use SilverStripe\VersionedAdmin\Forms\HistoryViewerField;

/**
 * Class MenuSet
 */
class MenuSet extends DataObject implements PermissionProvider, CMSPreviewable
{
    use EnsuresVersion;

    private static string $table_name = 'MenuSet';

    private static array $db = [
        // The reference used in templates, e.g. $MenuSet('MainMenu'). Never contains spaces.
        'Name' => 'Varchar(255)',
        // What editors call this menu. Free text, and safe to change at any time.
        'Title' => 'Varchar(255)',
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

    /**
     * Publishing a set publishes its items, so a menu is always published as a whole.
     */
    private static array $owns = [
        'MenuItems',
    ];

    private static array $searchable_fields = [
        'Name',
        'Description'
    ];

    private static string $default_sort = 'Sort ASC';

    /**
     * The page the preview panel opens on. Menus render site wide, so the home page is the
     * default. Point this at another URL if a menu is better judged somewhere else.
     */
    private static ?string $preview_url = null;

    /**
     * Offer draft and published toggles in the preview, the way pages do.
     */
    private static bool $show_stage_link = true;

    private static bool $show_live_link = true;

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

        $name = static::normaliseName($this->Name);

        if ($name === '') {
            return $result;
        }

        // A menu listed in default_sets is referenced by name in config and in templates, so its
        // name is fixed once created
        $changed = $this->getChangedFields(true);
        $previous = $changed['Name']['before'] ?? null;

        if ($previous && $previous !== $name && in_array($previous, $this->getDefaultSetNames())) {
            $result->addError(
                _t(
                    __CLASS__ . '.NameLocked',
                    'The menu "{name}" is required by this site, so its name cannot be changed',
                    ['name' => $previous]
                ),
                ValidationResult::TYPE_ERROR
            );

            return $result;
        }

        $existing = MenuManagerTemplateProvider::getMenuSet($name);

        if ($existing && $existing->ID !== $this->ID && $existing->Name === $name) {
            $result->addError(
                _t(
                    __CLASS__ . 'AlreadyExists',
                    'A Menu Set with the Name "{name}" already exists',
                    ['name' => $name]
                ),
                ValidationResult::TYPE_ERROR
            );
        }

        return $result;
    }


    /**
     * Names are used as references, so they never contain whitespace.
     */
    public static function normaliseName(?string $name): string
    {
        return preg_replace('/\s+/', '', (string) $name);
    }


    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $this->Name = static::normaliseName($this->Name);

        // A menu created before Title existed reads by its name
        if (!$this->Title && $this->Name) {
            $this->Title = $this->Name;
        }
    }


    /**
     * What editors see. Falls back to the reference name.
     */
    public function getTitle(): string
    {
        return (string) ($this->getField('Title') ?: $this->getField('Name'));
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
        // Backwards compatibility for duplicate default sets. A menu added in the CMS has no
        // name until the editor gives it one, so there is nothing to look up.
        $name = static::normaliseName($this->Name);
        $existing = $name === '' ? null : MenuManagerTemplateProvider::getMenuSet($name);
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
     * The top level items of this menu, in order.
     *
     * Nested items are excluded, so a template that loops $MenuItems renders one level and can
     * descend into $Children where it wants to. Use getAllMenuItems() for every item regardless
     * of nesting.
     *
     * @return HasManyList<MenuItem>
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- overrides the has_many accessor
    public function MenuItems(): HasManyList
    {
        return $this->getAllMenuItems()->filter('ParentItemID', 0);
    }


    /**
     * Every item in this menu, nested or not.
     *
     * @return HasManyList<MenuItem>
     */
    public function getAllMenuItems(): HasManyList
    {
        return $this->getComponents('MenuItems')->sort(['Sort' => 'ASC', 'ID' => 'ASC']);
    }


    /**
     * @return HasManyList<MenuItem>
     */
    public function getChildren(): HasManyList
    {
        return $this->MenuItems();
    }


    /**
     * Number of links in this menu, at any level.
     */
    public function getMenuItemCount(): int
    {
        return $this->getAllMenuItems()->count();
    }


    /**
     * How this menu reads in the CMS menu picker: its name, plus a note when it has changes that
     * are not live yet.
     */
    public function getMenuAdminTitle(): string
    {
        $title = $this->getTitle() ?: _t(__CLASS__ . '.UNTITLED', 'Untitled menu');

        if (!$this->hasExtension(Versioned::class) || !$this->isInDB()) {
            return $title;
        }

        if (!$this->isPublished()) {
            return sprintf('%s (%s)', $title, _t(__CLASS__ . '.DRAFT', 'draft'));
        }

        return $title;
    }


    /**
     * Check if this menu set appears in the default sets config
     * @return bool
     */
    public function isDefaultSet(): bool
    {
        $name = static::normaliseName($this->Name);

        return $name !== '' && in_array($name, $this->getDefaultSetNames());
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

        $this->publishUnpublishedMenus();
    }


    /**
     * Publish any menu that is not fully live yet.
     *
     * Menus became versioned in 5.1. Two things have to happen for an existing site to keep
     * working after that upgrade:
     *
     *  - rows written before versioning have Version 0 and no version history at all. Publishing
     *    compares the draft and live version numbers, so 0 against 0 reads as "no change" and
     *    publishing would quietly do nothing. Writing the record once gives it version 1.
     *  - the menu is then published recursively, which takes its items with it.
     *
     * Safe to run repeatedly: menus that are already live are left alone.
     */
    protected function publishUnpublishedMenus(): int
    {
        if (!$this->hasExtension(Versioned::class)) {
            return 0;
        }

        $published = 0;

        foreach (Versioned::get_by_stage(MenuSet::class, Versioned::DRAFT) as $set) {
            if (!$set->needsInitialPublish()) {
                continue;
            }

            $set->ensureVersionExists();

            foreach ($set->getAllMenuItems() as $item) {
                $item->ensureVersionExists();
            }

            $set->publishRecursive();
            $published++;
        }

        if ($published) {
            DB::alteration_message(
                sprintf('Published %d menu(s) that pre-date versioning', $published),
                'changed'
            );
        }

        return $published;
    }


    /**
     * Whether this menu, or anything in it, has never been published.
     */
    public function needsInitialPublish(): bool
    {
        if (!$this->isPublished()) {
            return true;
        }

        foreach ($this->getAllMenuItems() as $item) {
            if (!$item->isPublished()) {
                return true;
            }
        }

        return false;
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
        // No title: the tree is the whole content of its tab, and a label column would only
        // narrow it
        return TreeField::create(
            'MenuItems',
            '',
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

        if ($this->isInDB()) {
            $fields->addFieldToTab('Root.Main', $this->getMenuItemsField());
            $fields->addFieldToTab('Root.Settings', $this->getTitleField());
            $fields->addFieldToTab('Root.Settings', $this->getNameField());
            $fields->addFieldToTab('Root.Settings', $this->getDescriptionField());

            if (class_exists(HistoryViewerField::class)) {
                $fields->addFieldToTab(
                    'Root.History',
                    HistoryViewerField::create('MenuSetHistory')
                        ->setTitle(_t(__CLASS__ . '.HISTORY', 'History'))
                );
            }
        } else {
            $fields->addFieldToTab('Root.Main', $this->getTitleField());
            $fields->addFieldToTab('Root.Main', $this->getNameField());
            $fields->addFieldToTab('Root.Main', $this->getDescriptionField());
        }

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }


    protected function getTitleField(): TextField
    {
        return TextField::create('Title', _t(__CLASS__ . '.DB_Title', 'Title'))
            ->setDescription(_t(
                __CLASS__ . '.DB_Title_Description',
                'What this menu is called in the CMS. Safe to change at any time.'
            ));
    }


    protected function getNameField(): FormField
    {
        $field = TextField::create('Name', _t(__CLASS__ . '.DB_Name', 'Name'));

        // Templates and config refer to a menu by name, so it is fixed once one has been set.
        // A menu added in the CMS starts without one, so the editor gets to choose it.
        if ($this->isInDB() && $this->getField('Name')) {
            return $field
                ->setDescription(_t(
                    __CLASS__ . '.DB_Name_Locked',
                    'The reference templates use. It cannot be changed once the menu is saved.'
                ))
                ->performReadonlyTransformation();
        }

        return $field->setDescription(_t(
            __CLASS__ . '.DB_Name_Description',
            'The reference templates use. Spaces are removed, and it cannot be changed once the '
            . 'menu is saved.'
        ));
    }


    protected function getDescriptionField(): TextareaField
    {
        return TextareaField::create(
            'Description',
            _t(__CLASS__ . '.DB_Description', 'Description')
        );
    }


    /**
     * The buttons shown at the bottom of the Menus section.
     */
    public function getCMSActions(): FieldList
    {
        $actions = FieldList::create();
        $member = Security::getCurrentUser();
        $versioned = $this->hasExtension(Versioned::class);

        if ($this->isInDB() && $this->canEdit($member)) {
            $actions->push(
                FormAction::create('save', _t(__CLASS__ . '.SAVE', 'Save'))
                    ->addExtraClass('btn btn-primary font-icon-save')
                    ->setUseButtonTag(true)
            );
        }

        if ($versioned && $this->isInDB() && $this->canPublish()) {
            $hasChanges = $this->hasDraftChanges();

            $publish = FormAction::create(
                'publish',
                $hasChanges
                    ? _t(__CLASS__ . '.PUBLISH', 'Publish menu')
                    : _t(__CLASS__ . '.PUBLISHED', 'Published')
            )
                ->addExtraClass('btn btn-outline-primary font-icon-rocket')
                ->setUseButtonTag(true);

            if (!$hasChanges) {
                $publish->setDisabled(true);
            }

            $actions->push($publish);

            if ($this->isPublished() && $this->canUnpublish()) {
                $actions->push(
                    FormAction::create('unpublish', _t(__CLASS__ . '.UNPUBLISH', 'Unpublish'))
                        ->addExtraClass('btn btn-outline-danger font-icon-cancel-circled')
                        ->setUseButtonTag(true)
                );
            }
        }

        if (MenuAdmin::config()->get('enable_cms_create') && $this->canCreate($member)) {
            $actions->push(
                FormAction::create('addMenuSet', _t(MenuAdmin::class . '.ADD_MENU', 'Add menu'))
                    ->addExtraClass('btn btn-secondary font-icon-plus-circled')
                    ->setUseButtonTag(true)
            );
        }

        if ($this->isInDB() && $this->canDelete($member)) {
            $actions->push(
                FormAction::create('delete', _t(MenuAdmin::class . '.DELETE_MENU', 'Delete menu'))
                    ->addExtraClass('btn btn-outline-danger font-icon-trash-bin')
                    ->setAttribute('data-confirm-message', _t(
                        MenuAdmin::class . '.CONFIRM_DELETE',
                        'Delete "{title}" and all {count} of its links?',
                        ['title' => $this->getTitle(), 'count' => $this->getMenuItemCount()]
                    ))
                    ->setUseButtonTag(true)
            );
        }

        $this->extend('updateCMSActions', $actions);

        return $actions;
    }


    /**
     * Whether this menu, or any link in it, has changes that are not live yet.
     */
    public function hasDraftChanges(): bool
    {
        if (!$this->hasExtension(Versioned::class) || !$this->isInDB()) {
            return false;
        }

        if (!$this->isPublished() || $this->isModifiedOnDraft()) {
            return true;
        }

        foreach ($this->getAllMenuItems() as $item) {
            if (!$item->isPublished() || $item->isModifiedOnDraft()) {
                return true;
            }
        }

        return false;
    }


    /**
     * {@inheritDoc}
     */
    public function onBeforeDelete()
    {
        $menuItems = $this->getAllMenuItems();

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
            'Title' => _t(__CLASS__ . '.DB_Title', 'Title'),
            'Name' => _t(__CLASS__ . '.DB_Name', 'Name'),
            'Description' => _t(__CLASS__ . '.DB_Description', 'Description'),
            'MenuItems.Count' => _t(__CLASS__ . '.DB_Items', 'Items')
        ];
    }


    /**
     * Where the CMS preview panel points.
     *
     * Menus are not pages, so there is nothing to preview in isolation. The panel opens the site
     * itself, which is where the menu is actually seen. Because menus are versioned, the
     * navigator's draft and published toggles work as they do for pages.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- defined by CMSPreviewable
    public function PreviewLink($action = null): ?string
    {
        if (!$this->canView()) {
            return null;
        }

        // The site root rather than the home page's URL segment: Silverstripe redirects the
        // segment to the root, and the preview iframe loses its stage parameters on the way
        $link = static::config()->get('preview_url') ?: Director::absoluteBaseURL();

        $this->extend('updatePreviewLink', $link, $action);

        return $link;
    }

    public function getMimeType(): string
    {
        return 'text/html';
    }

    public function getCMSEditLink(): ?string
    {
        if (!$this->isInDB()) {
            return null;
        }

        return Controller::join_links(
            Director::baseURL(),
            MenuAdmin::singleton()->Link(),
            '?MenuSetID=' . $this->ID
        );
    }


    public function asArray(): array
    {
        return [
            'name' => $this->Name,
            'description' => $this->Description,
            'items' => array_map(
                fn (MenuItem $item) => $item->asArray(),
                $this->MenuItems()->toArray()
            ),
        ];
    }
}
