<?php

namespace Heyday\MenuManager;

use Akqa\SilverStripe\TreeField\Form\TreeField;
use Heyday\MenuManager\TreeField\MenuTreeSource;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Security\Security;

/**
 * The Menus section of the CMS.
 *
 * The whole structure is one tree: every menu set, with its links nested underneath. Sets and
 * links are added, renamed, re-ordered, nested and deleted in place, and the selected record's own
 * CMS fields sit alongside the tree.
 */
class MenuAdmin extends LeftAndMain
{
    private static string $url_segment = 'menu-manager';

    private static string $menu_title = 'Menus';

    private static string $menu_icon_class = 'font-icon-link';

    /**
     * Whether menu sets can be created and deleted from the CMS. Turn this off on a site where
     * the sets are fixed by configuration.
     */
    private static bool $enable_cms_create = true;

    public function getEditForm($id = null, $fields = null): Form
    {
        $fields = FieldList::create();

        if (!MenuSet::singleton()->canView()) {
            $fields->push(LiteralField::create(
                'MenuPermissionMessage',
                sprintf(
                    '<p class="message warning">%s</p>',
                    _t(__CLASS__ . '.NO_PERMISSION', 'You do not have permission to manage menus.')
                )
            ));

            $form = Form::create($this, 'EditForm', $fields, FieldList::create());
            $form->addExtraClass('cms-edit-form fill-height');
            $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));

            return $form;
        }

        $tree = TreeField::create(
            'Menus',
            '',
            MenuTreeSource::KEY
        );

        $fields->push($tree);

        $form = Form::create($this, 'EditForm', $fields, FieldList::create());
        $form->addExtraClass('cms-edit-form cms-panel-padded center flexbox-area-grow fill-height');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');

        $this->extend('updateEditForm', $form);

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
