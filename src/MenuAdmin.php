<?php

namespace Heyday\MenuManager;

use Akqa\SilverStripe\TreeField\Form\TreeField;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

/**
 * The Menus section of the CMS.
 *
 * One menu is open at a time, chosen with the selector at the top of the form. Its links are
 * managed in a tree, and whatever is selected in that tree has its own fields open alongside it.
 *
 * Menus are versioned. Adding, editing and moving links changes the draft only; the Publish
 * button sends the whole menu live.
 */
class MenuAdmin extends LeftAndMain
{
    /**
     * Remembers the menu the member last had open, so the section reopens where they left off.
     */
    public const SET_COOKIE = 'menumanager-last-set';

    private static string $url_segment = 'menu-manager';

    private static string $menu_title = 'Menus';

    private static string $menu_icon_class = 'font-icon-link';

    private static array $allowed_actions = [
        'EditForm',
        'save',
        'publish',
        'unpublish',
        'addMenuSet',
        'deleteMenuSet',
    ];

    /**
     * Whether menus can be created and deleted from the CMS. Turn this off on a site where the
     * sets are fixed by configuration.
     */
    private static bool $enable_cms_create = true;

    /**
     * Every menu the current member may see, in a stable order.
     *
     * @return DataList<MenuSet>
     */
    public function getMenuSets(): DataList
    {
        return MenuSet::get()->sort(['Sort' => 'ASC', 'Name' => 'ASC']);
    }

    /**
     * The menu currently being edited.
     *
     * Falls back through the request, the member's last choice, and finally the menu edited most
     * recently, so the section always opens on something useful.
     */
    public function getCurrentMenuSet(): ?MenuSet
    {
        $sets = $this->getMenuSets();

        $requested = (string) ($this->getRequest()->requestVar('MenuSetID') ?: '');

        if (ctype_digit($requested)) {
            $set = $sets->byID((int) $requested);

            if ($set) {
                $this->rememberMenuSet($set);

                return $set;
            }
        }

        $remembered = (string) (Cookie::get(self::SET_COOKIE) ?: '');

        if (ctype_digit($remembered)) {
            $set = $sets->byID((int) $remembered);

            if ($set) {
                return $set;
            }
        }

        return $sets->sort('LastEdited', 'DESC')->first();
    }

    protected function rememberMenuSet(MenuSet $set): void
    {
        Cookie::set(self::SET_COOKIE, (string) $set->ID, 30, null, null, false, false);
    }

    public function getEditForm($id = null, $fields = null): Form
    {
        if (!MenuSet::singleton()->canView()) {
            return $this->getMessageForm(
                _t(__CLASS__ . '.NO_PERMISSION', 'You do not have permission to manage menus.')
            );
        }

        $set = $this->getCurrentMenuSet();
        $fields = FieldList::create(TabSet::create('Root'));

        $fields->addFieldToTab('Root.Main', $this->getMenuSetSelectorField($set));

        if (!$set) {
            $fields->addFieldToTab('Root.Main', LiteralField::create(
                'NoMenus',
                sprintf(
                    '<p class="message notice">%s</p>',
                    _t(__CLASS__ . '.NO_MENUS', 'There are no menus yet. Add one to get started.')
                )
            ));
        } else {
            $tree = TreeField::create(
                'MenuItems',
                '',
                MenuItemTreeSource::KEY,
                (int) $set->ID
            );

            $fields->addFieldToTab('Root.Main', $tree);

            $fields->addFieldToTab('Root.Settings', TextField::create(
                'Name',
                _t(MenuSet::class . '.DB_Name', 'Name')
            ));
            $fields->addFieldToTab('Root.Settings', TextareaField::create(
                'Description',
                _t(MenuSet::class . '.DB_Description', 'Description')
            ));

            $set->invokeWithExtensions('updateMenuAdminFields', $fields);
        }

        $form = Form::create($this, 'EditForm', $fields, $this->getFormActions($set));
        $form->addExtraClass('cms-edit-form cms-panel-padded center flexbox-area-grow fill-height');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');

        if ($set) {
            $form->loadDataFrom($set);
            $form->Fields()->dataFieldByName('MenuSetID')?->setValue($set->ID);
        }

        $this->extend('updateEditForm', $form);

        return $form;
    }

    /**
     * The menu picker. Changing it reloads the section for that menu.
     */
    protected function getMenuSetSelectorField(?MenuSet $current): DropdownField
    {
        $source = [];

        foreach ($this->getMenuSets() as $set) {
            $source[$set->ID] = $set->getMenuAdminTitle();
        }

        $field = DropdownField::create(
            'MenuSetID',
            _t(__CLASS__ . '.CURRENT_MENU', 'Menu'),
            $source,
            $current?->ID
        );

        $field->addExtraClass('menu-admin__selector');
        $field->setAttribute('data-menu-admin-link', $this->Link());
        $field->setEmptyString(_t(__CLASS__ . '.CHOOSE_MENU', 'Choose a menu'));

        return $field;
    }

    /**
     * @return FieldList
     */
    protected function getFormActions(?MenuSet $set): FieldList
    {
        $actions = FieldList::create();
        $member = Security::getCurrentUser();

        if ($set && $set->canEdit($member)) {
            $actions->push(
                FormAction::create('save', _t(__CLASS__ . '.SAVE', 'Save'))
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn-primary font-icon-save')
            );

            if ($set->hasExtension(Versioned::class) && $set->canPublish()) {
                $publishTitle = $set->isPublished() && !$this->menuIsModified($set)
                    ? _t(__CLASS__ . '.PUBLISHED', 'Published')
                    : _t(__CLASS__ . '.PUBLISH', 'Publish menu');

                $publish = FormAction::create('publish', $publishTitle)
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn-outline-primary font-icon-rocket');

                if ($set->isPublished() && !$this->menuIsModified($set)) {
                    $publish->setDisabled(true);
                }

                $actions->push($publish);

                if ($set->isPublished()) {
                    $actions->push(
                        FormAction::create('unpublish', _t(__CLASS__ . '.UNPUBLISH', 'Unpublish'))
                            ->setUseButtonTag(true)
                            ->addExtraClass('btn-outline-danger font-icon-cancel-circled')
                    );
                }
            }
        }

        if ($this->config()->get('enable_cms_create') && MenuSet::singleton()->canCreate($member)) {
            $actions->push(
                FormAction::create('addMenuSet', _t(__CLASS__ . '.ADD_MENU', 'Add menu'))
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn-secondary font-icon-plus-circled')
            );
        }

        if ($set && $set->canDelete($member)) {
            $actions->push(
                FormAction::create('deleteMenuSet', _t(__CLASS__ . '.DELETE_MENU', 'Delete menu'))
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn-outline-danger font-icon-trash-bin')
            );
        }

        $this->extend('updateFormActions', $actions, $set);

        return $actions;
    }

    /**
     * Whether the menu or any of its links has draft changes waiting to be published.
     */
    public function menuIsModified(MenuSet $set): bool
    {
        if (!$set->hasExtension(Versioned::class)) {
            return false;
        }

        if (!$set->isPublished() || $set->isModifiedOnDraft()) {
            return true;
        }

        foreach ($set->MenuItems() as $item) {
            if (!$item->isPublished() || $item->isModifiedOnDraft()) {
                return true;
            }
        }

        return false;
    }

    public function save(array $data, Form $form): HTTPResponse
    {
        $set = $this->getCurrentMenuSet();

        if (!$set || !$set->canEdit()) {
            return $this->httpError(403);
        }

        // The menu picker shares the form but is navigation, not data
        $saveable = array_values(array_diff(
            array_keys($form->Fields()->saveableFields()),
            ['MenuSetID']
        ));

        $form->saveInto($set, $saveable);

        $validation = $set->validate();

        if (!$validation->isValid()) {
            return $this->respondWithMessage($form, $validation);
        }

        $set->write();

        return $this->reloadForm(_t(__CLASS__ . '.SAVED', 'Saved'));
    }

    public function publish(array $data, Form $form): HTTPResponse
    {
        $set = $this->getCurrentMenuSet();

        if (!$set || !$set->canPublish()) {
            return $this->httpError(403);
        }

        // Owning the items means one call sends the whole menu live
        $set->publishRecursive();

        return $this->reloadForm(_t(__CLASS__ . '.PUBLISHED_MESSAGE', 'Published menu'));
    }

    public function unpublish(array $data, Form $form): HTTPResponse
    {
        $set = $this->getCurrentMenuSet();

        if (!$set || !$set->canUnpublish()) {
            return $this->httpError(403);
        }

        $set->doUnpublish();

        return $this->reloadForm(_t(__CLASS__ . '.UNPUBLISHED_MESSAGE', 'Unpublished menu'));
    }

    public function addMenuSet(array $data, Form $form): HTTPResponse
    {
        if (!$this->config()->get('enable_cms_create') || !MenuSet::singleton()->canCreate()) {
            return $this->httpError(403);
        }

        $set = MenuSet::create();
        $set->Name = $this->getUniqueSetName();
        $set->Sort = $this->getMenuSets()->count() + 1;
        $set->write();

        $this->rememberMenuSet($set);

        return $this->reloadForm(_t(__CLASS__ . '.ADDED', 'Menu added'), $set);
    }

    public function deleteMenuSet(array $data, Form $form): HTTPResponse
    {
        $set = $this->getCurrentMenuSet();

        if (!$set || !$set->canDelete()) {
            return $this->httpError(403);
        }

        if ($set->hasExtension(Versioned::class) && $set->isPublished()) {
            $set->doArchive();
        } else {
            $set->delete();
        }

        Cookie::force_expiry(self::SET_COOKIE);

        return $this->reloadForm(_t(__CLASS__ . '.DELETED', 'Menu deleted'));
    }

    /**
     * Menu names have to be unique, so a new menu cannot simply be called "New menu".
     */
    protected function getUniqueSetName(): string
    {
        $base = _t(MenuSet::class . '.NEW_SET', 'New menu');
        $name = $base;
        $suffix = 1;

        while (MenuSet::get()->filter('Name', $name)->exists()) {
            $suffix++;
            $name = sprintf('%s %d', $base, $suffix);
        }

        return $name;
    }

    /**
     * Send the rebuilt form back to the CMS, with a message for the member.
     */
    protected function reloadForm(string $message, ?MenuSet $set = null): HTTPResponse
    {
        if ($set) {
            $this->getRequest()->offsetSet('MenuSetID', (string) $set->ID);
        }

        $form = $this->getEditForm();
        $form->setMessage($message, ValidationResult::TYPE_GOOD);

        return $this->getSchemaResponse($this->Link('schema/EditForm'), $form);
    }

    protected function respondWithMessage(Form $form, ValidationResult $result): HTTPResponse
    {
        return $this->getSchemaResponse($this->Link('schema/EditForm'), $form, $result);
    }

    protected function getMessageForm(string $message): Form
    {
        $fields = FieldList::create(
            LiteralField::create(
                'MenuPermissionMessage',
                sprintf('<p class="message warning">%s</p>', $message)
            )
        );

        $form = Form::create($this, 'EditForm', $fields, FieldList::create());
        $form->addExtraClass('cms-edit-form fill-height');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));

        return $form;
    }

    public function canView($member = null): bool
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if (!parent::canView($member)) {
            return false;
        }

        return MenuSet::singleton()->canView($member);
    }
}
