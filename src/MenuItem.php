<?php

namespace Heyday\MenuManager;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class MenuItem
 */
class MenuItem extends DataObject implements PermissionProvider
{
    /**
     * @var string
     */
    private static string $table_name = 'MenuItem';

    private static array $db = [
        'MenuTitle' => 'Varchar(255)',
        'Link' => 'Text',
        'Sort' => 'Int',
        'IsNewWindow' => 'Boolean'
    ];

    /**
     * @var array
     */
    private static array $has_one = [
        'Page' => SiteTree::class, // page the MenuItem refers to
        'MenuSet' => MenuSet::class, // parent MenuSet
    ];

    /**
     * @var array
     */
    private static array $searchable_fields = [
        'MenuTitle',
        'Page.Title'
    ];

    /**
     * @return string
     */
    public function IsNewWindowNice(): string
    {
        return $this->IsNewWindow
            ? _t('SilverStripe\\Forms\\CheckboxField.YESANSWER', 'Yes')
            : _t('SilverStripe\\Forms\\CheckboxField.NOANSWER', 'No');
    }

    /**
     * @var string
     */
    private static string $default_sort = 'Sort';

    /**
     * @return array
     */
    public function providePermissions(): array
    {
        return [
            'MANAGE_MENU_ITEMS' => _t(__CLASS__ . '.ManageMenuItems', 'Manage Menu Items')
        ];
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

        return Permission::checkMember($member, 'MANAGE_MENU_ITEMS');
    }

    /**
     * @param mixed $member
     * @return boolean
     */
    public function canDelete($member = null): bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_ITEMS');
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

        return Permission::check('MANAGE_MENU_ITEMS');
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

        return Permission::check('MANAGE_MENU_ITEMS');
    }

    /**
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $fields = FieldList::create(TabSet::create('Root'));

        $fields->addFieldsToTab(
            'Root.main',
            [
                TextField::create(
                    'MenuTitle',
                    _t(__CLASS__ . '.DB_MenuTitle', 'Link Label')
                )->setDescription(
                    _t(
                        __CLASS__ . '.DB_MenuTitle_Description',
                        'If left blank, will default to the selected page\'s name.'
                    )
                ),

                TreeDropdownField::create(
                    'PageID',
                    _t(__CLASS__ . '.DB_PageID', 'Page on this site'),
                    SiteTree::class
                )->setDescription(
                    _t(
                        __CLASS__ . '.DB_PageID_Description',
                        'Leave blank if you wish to manually specify the URL below.'
                    )
                ),

                TextField::create(
                    'Link',
                    _t(__CLASS__ . '.DB_Link', 'URL')
                )->setDescription(
                    _t(
                        __CLASS__ . '.DB_Link_Description',
                        'Enter a full URL to link to another website.'
                    )
                ),

                CheckboxField::create(
                    'IsNewWindow',
                    _t(__CLASS__ . '.DB_IsNewWindow', 'Open in a new window?')
                ),
            ]
        );

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * @return mixed
     */
    public function Parent()
    {
        return $this->MenuSet();
    }

    /**
     * Attempts to return the $field from this MenuItem
     * If $field is not found or it is not set then attempts
     * to return a similar field on the associated Page
     * (if there is one)
     *
     * @param string $field
     * @return mixed
     */
    public function __get($field)
    {
        $default = parent::__get($field);

        if ($default || $field === 'ID') {
            return $default;
        } else {
            $page = $this->Page();

            if ($page instanceof DataObject) {
                if ($page->hasMethod($field)) {
                    return $page->$field();
                } else {
                    return $page->$field;
                }
            }
        }
    }

    /**
     * @return mixed
     */
    public function getTitle(): string
    {
        return $this->MenuTitle;
    }

    /**
     * Checks to see if a page has been chosen and if so sets Link to null
     * This means that used in conjunction with the __get method above
     * calling $menuItem->Link won't return the Link field of this MenuItem
     * but rather call the Link method on the associated Page
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->PageID != 0) {
            $this->Link = null;
        }
    }

    public function summaryFields()
    {
        return [
            'MenuTitle' => _t(__CLASS__ . '.Label', 'Label'),
            'Page.Title' => _t(__CLASS__ . '.PageTitle', 'Page Title'),
            'Link' => _t(__CLASS__ . '.DB_Link', 'Link'),
            'IsNewWindowNice' => _t(__CLASS__ . '.NewTab', 'Opens in a new tab?'),
        ];
    }
}
