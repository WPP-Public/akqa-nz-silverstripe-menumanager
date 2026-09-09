<?php

namespace Heyday\MenuManager;

use SilverStripe\Admin\SingleRecordAdmin;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

/**
 * The Menus section of the CMS.
 *
 * One menu is open at a time, chosen with the picker at the top of the form. Its links are
 * managed in a tree, and whatever is selected in that tree has its own fields open alongside it.
 *
 * Menus are versioned. Adding, editing and moving links changes the draft only; Publish sends the
 * whole menu live.
 */
class MenuAdmin extends SingleRecordAdmin
{
    /**
     * Remembers the menu the member last had open, so the section reopens where they left off.
     */
    public const SET_COOKIE = 'menumanager-last-set';

    private static string $url_segment = 'menu-manager';

    private static string $menu_title = 'Menus';

    private static string $menu_icon_class = 'font-icon-link';

    private static ?string $model_class = MenuSet::class;

    /**
     * There are many menus, and the picker decides which one is open.
     */
    private static bool $restrict_to_single_record = false;

    private static bool $allow_new_record = false;

    private static array $allowed_actions = [
        'publish',
        'unpublish',
        'addMenuSet',
    ];

    /**
     * Whether menus can be created and deleted from the CMS. Turn this off on a site where the
     * menus are fixed by configuration.
     */
    private static bool $enable_cms_create = true;

    /**
     * Every menu the current member may see, in the order the picker lists them.
     *
     * @return DataList<MenuSet>
     */
    public function getMenuSets(): DataList
    {
        return MenuSet::get()->sort(['Sort' => 'ASC', 'Name' => 'ASC']);
    }

    /**
     * The menu being edited.
     *
     * Falls back through the request, the member's last choice, and finally the menu edited most
     * recently, so the section always opens on something useful.
     */
    public function currentRecordID(): ?int
    {
        $sets = $this->getMenuSets();
        $request = $this->getRequest();

        foreach (['MenuSetID', 'ID'] as $param) {
            $requested = (string) ($request->requestVar($param) ?: '');

            if (ctype_digit($requested) && $sets->byID((int) $requested)) {
                $this->rememberMenuSet((int) $requested);

                return (int) $requested;
            }
        }

        $remembered = (string) (Cookie::get(self::SET_COOKIE) ?: '');

        if (ctype_digit($remembered) && $sets->byID((int) $remembered)) {
            return (int) $remembered;
        }

        $latest = $sets->sort('LastEdited', 'DESC')->first();

        return $latest ? (int) $latest->ID : null;
    }

    public function getCurrentMenuSet(): ?MenuSet
    {
        $id = $this->currentRecordID();

        return $id ? $this->getMenuSets()->byID($id) : null;
    }

    protected function rememberMenuSet(int $id): void
    {
        Cookie::set(self::SET_COOKIE, (string) $id, 30, null, null, false, false);
    }

    public function getEditForm($id = null, $fields = null): ?Form
    {
        $form = parent::getEditForm($id, $fields);

        if (!$form) {
            return $form;
        }

        // The picker belongs above everything else in the form
        $form->Fields()->insertBefore(
            $form->Fields()->first()?->getName() ?: '',
            $this->getMenuSetSelectorField()
        );

        $form->addExtraClass('menu-admin');

        return $form;
    }

    /**
     * The menu picker. Changing it reopens the section on that menu.
     */
    protected function getMenuSetSelectorField(): DropdownField
    {
        $source = [];

        foreach ($this->getMenuSets() as $set) {
            $source[$set->ID] = $set->getMenuAdminTitle();
        }

        $field = DropdownField::create(
            'MenuSetID',
            _t(__CLASS__ . '.CURRENT_MENU', 'Menu'),
            $source,
            $this->currentRecordID()
        );

        $field->addExtraClass('menu-admin__selector');
        $field->setAttribute('data-menu-admin-link', $this->Link());
        $field->setEmptyString(_t(__CLASS__ . '.CHOOSE_MENU', 'Choose a menu'));

        return $field;
    }

    /**
     * Whether the menu or any of its links has draft changes waiting to be published.
     */
    public function menuIsModified(MenuSet $set): bool
    {
        return $set->hasDraftChanges();
    }

    public function publish(array $data, Form $form): HTTPResponse
    {
        $set = $this->getCurrentMenuSet();

        if (!$set || !$set->canPublish()) {
            $this->httpError(403);
        }

        // Owning the links means one call sends the whole menu live
        $set->publishRecursive();

        return $this->reloadForm(
            _t(__CLASS__ . '.PUBLISHED_MESSAGE', 'Published menu'),
            (int) $set->ID
        );
    }

    public function unpublish(array $data, Form $form): HTTPResponse
    {
        $set = $this->getCurrentMenuSet();

        if (!$set || !$set->canUnpublish()) {
            $this->httpError(403);
        }

        $set->doUnpublish();

        return $this->reloadForm(
            _t(__CLASS__ . '.UNPUBLISHED_MESSAGE', 'Unpublished menu'),
            (int) $set->ID
        );
    }

    public function addMenuSet(array $data, Form $form): HTTPResponse
    {
        if (!$this->config()->get('enable_cms_create') || !MenuSet::singleton()->canCreate()) {
            $this->httpError(403);
        }

        // No Name yet: it is the reference templates use and cannot be changed later, so the
        // editor chooses it on the Settings tab rather than being given a generated one
        $set = MenuSet::create();
        $set->Title = _t(MenuSet::class . '.NEW_SET', 'New menu');
        $set->Sort = $this->getMenuSets()->count() + 1;
        $set->write();

        $this->rememberMenuSet((int) $set->ID);

        return $this->reloadForm(
            _t(__CLASS__ . '.ADDED', 'Menu added'),
            (int) $set->ID
        );
    }

    /**
     * Deleting a menu archives it, so it comes off the live site with its links rather than
     * leaving them orphaned there.
     */
    public function delete(array $data, Form $form): HTTPResponse
    {
        $set = $this->getCurrentMenuSet();

        if (!$set || !$set->canDelete()) {
            $this->httpError(403);
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
     * Send the rebuilt form back to the CMS with a message for the member.
     */
    protected function reloadForm(string $message, ?int $recordID = null): HTTPResponse
    {
        if ($recordID) {
            $this->getRequest()->offsetSet('MenuSetID', (string) $recordID);
        }

        $form = $this->getEditForm($recordID);

        if ($form) {
            $form->setMessage($message, 'good');
        }

        return $this->getSchemaResponse($this->Link('schema/EditForm'), $form);
    }

    public function getRecord($id): ?DataObject
    {
        if (!$id) {
            return null;
        }

        return parent::getRecord($id);
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
