<?php

namespace Heyday\MenuManager;

use SilverStripe\Admin\SingleRecordAdmin;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Model\List\ArrayList;
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

        // Only ever the menu the request names. With nothing named, the section shows its
        // landing view of every menu rather than guessing at one.
        foreach (['MenuSetID', 'ID'] as $param) {
            $requested = (string) ($request->requestVar($param) ?: '');

            if (ctype_digit($requested) && $sets->byID((int) $requested)) {
                return (int) $requested;
            }
        }

        return null;
    }

    public function getCurrentMenuSet(): ?MenuSet
    {
        $id = $this->currentRecordID();

        return $id ? $this->getMenuSets()->byID($id) : null;
    }

    public function getEditForm($id = null, $fields = null): ?Form
    {
        if (!$id && !$this->currentRecordID()) {
            return $this->getMenuListForm();
        }

        $form = parent::getEditForm($id, $fields);

        if (!$form) {
            return $form;
        }

        $form->Fields()->insertBefore(
            $form->Fields()->first()?->getName() ?: '',
            $this->getBackToMenusField()
        );

        // The CMS preview watches for an input named ID in the content panel and, when the page
        // in the preview does not match it, navigates the whole CMS to that page's edit form.
        // A menu is never the page being previewed, so that would throw the member out of this
        // section the moment the preview loaded. The record id travels as MenuSetID instead.
        $form->Fields()->removeByName('ID');
        $form->Fields()->push(
            HiddenField::create('MenuSetID')->setValue($this->currentRecordID())
        );

        $form->addExtraClass('menu-admin');

        return $form;
    }

    /**
     * Save the open menu.
     *
     * Overridden because LeftAndMain::save() finds its record through an input named ID, which
     * this form deliberately does not have.
     */
    public function save(array $data, Form $form): HTTPResponse
    {
        $set = $this->getCurrentMenuSet();

        if (!$set) {
            $this->httpError(404);
        }

        if (!$set->canEdit()) {
            $this->httpError(403);
        }

        // The record id travels with the form but is not data
        $saveable = array_values(array_diff(
            array_keys($form->Fields()->saveableFields()),
            ['MenuSetID']
        ));

        $form->saveInto($set, $saveable);

        $validation = $set->validate();

        if (!$validation->isValid()) {
            $form->setSessionValidationResult($validation);

            return $this->respondWith(
                $validation->getMessages()[0]['message'] ?? _t(__CLASS__ . '.INVALID', 'Not saved')
            );
        }

        $set->write();

        return $this->respondWith(_t(__CLASS__ . '.SAVED', 'Saved'));
    }

    /**
     * The section's landing view: every menu as a tile.
     */
    protected function getMenuListForm(): Form
    {
        $fields = FieldList::create(
            LiteralField::create('Menus', $this->renderMenuList())
        );

        $actions = FieldList::create();

        if ($this->config()->get('enable_cms_create') && MenuSet::singleton()->canCreate()) {
            $actions->push(
                FormAction::create('addMenuSet', _t(__CLASS__ . '.ADD_MENU', 'Add menu'))
                    ->addExtraClass('btn btn-primary font-icon-plus-circled')
                    ->setUseButtonTag(true)
            );
        }

        $form = Form::create($this, 'EditForm', $fields, $actions);
        $form->addExtraClass('cms-edit-form fill-height menu-admin menu-admin--list');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');

        $this->extend('updateMenuListForm', $form);

        return $form;
    }

    protected function renderMenuList(): string
    {
        return (string) $this
            ->customise(['Menus' => $this->getViewableMenuSets()])
            ->renderWith('Heyday/MenuManager/Includes/MenuAdmin_Menus');
    }

    /**
     * The menus the current member is allowed to see, for the landing view.
     *
     * @return ArrayList<MenuSet>
     */
    public function getViewableMenuSets(): ArrayList
    {
        $viewable = ArrayList::create();

        foreach ($this->getMenuSets() as $set) {
            if ($set->canView()) {
                $viewable->push($set);
            }
        }

        return $viewable;
    }

    /**
     * Takes the member back to the list of menus.
     */
    protected function getBackToMenusField(): LiteralField
    {
        return LiteralField::create('BackToMenus', sprintf(
            '<p class="menu-admin__back"><a href="%s" class="font-icon-left-open-big">%s</a></p>',
            Controller::join_links(Director::baseURL(), $this->Link()),
            _t(__CLASS__ . '.ALL_MENUS', 'All menus')
        ));
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

        return $this->respondWith(_t(__CLASS__ . '.PUBLISHED_MESSAGE', 'Published menu'));
    }

    public function unpublish(array $data, Form $form): HTTPResponse
    {
        $set = $this->getCurrentMenuSet();

        if (!$set || !$set->canUnpublish()) {
            $this->httpError(403);
        }

        $set->doUnpublish();

        return $this->respondWith(_t(__CLASS__ . '.UNPUBLISHED_MESSAGE', 'Unpublished menu'));
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

        // Open the new menu, which means changing which record the section is showing
        return $this->redirect(Controller::join_links(
            Director::baseURL(),
            $this->Link(),
            '?MenuSetID=' . $set->ID
        ));
    }

    /**
     * Deleting a menu archives it, so it comes off the live site with its links rather than
     * leaving them orphaned there.
     */
    public function delete(array $data, Form $form): HTTPResponse
    {
        // The menu the form names, not whichever one happens to be open. Acting on the current
        // record would let a stray submission delete a menu nobody chose.
        $id = (string) ($data['MenuSetID'] ?? $data['ID'] ?? '');

        if (!ctype_digit($id)) {
            $this->httpError(400, 'No menu was named');
        }

        $set = $this->getMenuSets()->byID((int) $id);

        if (!$set) {
            $this->httpError(404);
        }

        if (!$set->canDelete()) {
            $this->httpError(403);
        }

        if ($set->hasExtension(Versioned::class) && $set->isPublished()) {
            $set->doArchive();
        } else {
            $set->delete();
        }

        // Back to the list, since the menu that was open is gone
        return $this->redirect(Controller::join_links(Director::baseURL(), $this->Link()));
    }

    /**
     * Re-render the section with a message, the way the rest of the CMS answers a form action.
     */
    protected function respondWith(string $message): HTTPResponse
    {
        $this->getResponse()->addHeader('X-Status', rawurlencode($message));

        return $this->getResponseNegotiator()->respond($this->getRequest());
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
