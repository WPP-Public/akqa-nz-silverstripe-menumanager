<?php

namespace Heyday\MenuManager;

use Akqa\SilverStripe\TreeField\Contracts\TreeNodeProvider;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class MenuItem
 */
class MenuItem extends DataObject implements PermissionProvider, TreeNodeProvider
{
    /**
     * @var string
     */
    private static string $table_name = 'MenuItem';

    private static array $db = [
        'MenuTitle' => 'Varchar(255)',
        'Link' => 'Text',
        'Sort' => 'Int',
        'IsNewWindow' => 'Boolean',
        'Anchor' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static array $has_one = [
        'Page' => SiteTree::class, // page the MenuItem refers to
        'MenuSet' => MenuSet::class,
        'File' => File::class,
        // Nesting. Named ParentItem rather than Parent because getParent() already returns the
        // MenuSet this item belongs to.
        'ParentItem' => MenuItem::class,
    ];

    /**
     * @var array
     */
    private static array $has_many = [
        'Children' => MenuItem::class . '.ParentItem',
    ];

    /**
     * @var array
     */
    private static array $cascade_deletes = [
        'Children',
    ];

    /**
     * @var array
     */
    private static array $cascade_duplicates = [
        'Children',
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
    public function getIsNewWindowNice(): string
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
        $fields = FieldList::create(TabSet::create('Root')->addExtraClass('menu-manager-tabset'));

        $fields->addFieldsToTab(
            'Root.Main',
            [
                OptionsetField::create("LinkType", "", $this->getLinkTypes(), $this->getLinkType()),
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
                TextField::create('Anchor', _t(__CLASS__ . '.DB_Anchor', 'Anchor')),
                CheckboxField::create(
                    'IsNewWindow',
                    _t(__CLASS__ . '.DB_IsNewWindow', 'Open in a new window?')
                ),
                UploadField::create(
                    'File',
                    _t(__CLASS__ . '.DB_File', 'File')
                )->setFolderName('Uploads/menu-items'),
            ]
        );

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }


    public function getParent(): ?MenuSet
    {
        return $this->MenuSet();
    }

    /**
     * Direct children of this item, in menu order.
     *
     * @return HasManyList<MenuItem>
     */
    public function getChildItems(): HasManyList
    {
        return $this->Children()->sort(['Sort' => 'ASC', 'ID' => 'ASC']);
    }

    /**
     * Whether this item has anything nested under it.
     */
    public function hasChildItems(): bool
    {
        return $this->getChildItems()->exists();
    }

    /**
     * How deep this item sits, where a top level item is 1.
     */
    public function getMenuLevel(): int
    {
        $level = 1;
        $parent = $this->ParentItem();
        $seen = [$this->ID => true];

        while ($parent && $parent->exists() && !isset($seen[$parent->ID]) && $level < 20) {
            $seen[$parent->ID] = true;
            $level++;
            $parent = $parent->ParentItem();
        }

        return $level;
    }

    /**
     * The getTreeNode* methods below describe this item to the TreeField in the CMS.
     */
    public function getTreeNodeTitle(): string
    {
        return (string) $this->getTitle();
    }

    public function getTreeNodeSubtitle(): ?string
    {
        $url = $this->getURL();

        return $url !== '' ? $url : null;
    }

    public function getTreeNodeIcon(): ?string
    {
        return match ($this->getLinkType()) {
            'file' => 'font-icon-image',
            'external' => 'font-icon-external-link',
            default => 'font-icon-link',
        };
    }

    /**
     * @return array<int, array{text: string, type: string}>
     */
    public function getTreeNodeBadges(): array
    {
        $badges = [];

        if ($this->IsNewWindow) {
            $badges[] = [
                'text' => _t(__CLASS__ . '.NewTabBadge', 'New tab'),
                'type' => 'secondary',
            ];
        }

        // Read the raw columns - __get() falls back to the linked page when a field is empty,
        // which would make an item with no link of its own look like it has one
        $hasOwnLink = (int) $this->getField('PageID')
            || (int) $this->getField('FileID')
            || (string) $this->getField('Link') !== '';

        if (!$hasOwnLink) {
            $badges[] = [
                'text' => _t(__CLASS__ . '.NoLinkBadge', 'No link set'),
                'type' => 'warning',
            ];
        }

        $this->invokeWithExtensions('updateTreeNodeBadges', $badges);

        return $badges;
    }

    public function allowsTreeChildren(): bool
    {
        return true;
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
    public function __get(string $field): mixed
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

        return null;
    }

    /**
     * @return mixed
     */
    public function getTitle(): ?string
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

        if ($this->Anchor) {
            // strip out any leading #s
            $this->Anchor = preg_replace('/^#/', '', $this->Anchor);
        }
    }


    public function getLinkType(): string
    {
        if ($this->FileID && $this->FileID > 0) {
            $type = 'file';
        } elseif ($this->PageID && $this->PageID > 0 || !$this->Link || $this->Link == '/') {
            $type = 'internal';
        } else {
            $type = 'external';
        }

        $this->invokeWithExtensions('updateLinkType', $type);

        return $type;
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


    public function getLinkTypes(): array
    {
        $types = [
            'internal' => _t(__CLASS__ . '.INTERNAL', 'Link to an internal page'),
            'external' => _t(__CLASS__ . '.EXTERNAL', 'Link to an external page, email or phone number'),
            'file' => _t(__CLASS__ . '.FILE', 'Link to a file'),
        ];

        $this->invokeWithExtensions('updateLinkTypes', $types);

        return $types;
    }


    public function getLinkingMode()
    {
        if ($this->PageID) {
            return $this->Page()->LinkingMode();
        } else {
            return 'link';
        }
    }


    public function getURL(): string
    {
        if ($this->PageID) {
            $link = $this->Page()->Link();
        } elseif ($this->FileID) {
            $link = $this->File()->getURL();
        } else {
            $link = $this->Link;
        }

        if ($this->Anchor) {
            $link .= '#' . $this->Anchor;
        }

        $this->extend('updateURL', $link);

        return $link;
    }


    public function getAbsoluteURL(): string
    {
        if ($this->PageID) {
            $link = $this->Page()->AbsoluteLink();

            if ($this->Anchor) {
                $link .= '#' . $this->Anchor;
            }

            return $link;
        }

        return $this->getURL();
    }


    public function asArray(): array
    {
        $children = [];

        foreach ($this->getChildItems() as $child) {
            $children[] = $child->asArray();
        }

        return [
            'id' => $this->ID,
            'label' => $this->MenuTitle,
            'href' => $this->getURL(),
            'type' => $this->getLinkType(),
            'target' => $this->IsNewWindow ? '_blank' : '_self',
            'rel' => $this->IsNewWindow ? 'noopener noreferrer' : '',
            'children' => $children,
        ];
    }
}
