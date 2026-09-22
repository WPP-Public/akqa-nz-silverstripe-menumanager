<?php

namespace Heyday\MenuManager;

use Akqa\SilverStripe\TreeField\Contracts\TreeNodeProvider;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
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
    use EnsuresVersion;

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
     * Publishing an item publishes everything nested under it.
     *
     * @var array
     */
    private static array $owns = [
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
                // Use getField() so __get() does not fall through to Page::Link() and
                // pre-fill this with the internal page URL when editing.
                TextField::create(
                    'Link',
                    _t(__CLASS__ . '.DB_Link', 'URL')
                )->setValue($this->getField('Link'))
                ->setDescription(
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

    /**
     * Every menu item is a link, so a plain link icon would only restate that. The exceptions are
     * worth seeing at a glance: a phone number, an email address, and anything that opens in a
     * new tab.
     */
    public function getTreeNodeIcon(): ?string
    {
        // The raw column, so a link to a page is never mistaken for one of these
        $link = strtolower(trim((string) $this->getField('Link')));

        if (str_starts_with($link, 'tel:')) {
            return 'font-icon-mobile';
        }

        if (str_starts_with($link, 'mailto:')) {
            return 'font-icon-p-mail';
        }

        return $this->IsNewWindow ? 'font-icon-external-link' : null;
    }

    /**
     * @return array<int, array{text: string, type: string}>
     */
    public function getTreeNodeBadges(): array
    {
        // Opening in a new tab is shown by the row's icon, so it needs no badge as well
        $badges = [];

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
     * Fields that must never fall through to the linked page.
     *
     * Versioning columns in particular: an unsaved item has no Version of its own, and answering
     * with the page's would confuse the versioning layer.
     *
     * @var array
     */
    private static array $no_page_fallback = [
        'ID',
        'Version',
        'RecordID',
        'AuthorID',
        'PublisherID',
        'WasPublished',
        'WasDeleted',
        'WasDraft',
        'ParentItemID',
        'MenuSetID',
        'Sort',
        // Keep empty so the CMS URL field is not pre-filled with Page::Link().
        // Resolved destinations go through getURL() / AbsoluteURL.
        'Link',
    ];

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

        if ($default || in_array($field, static::config()->get('no_page_fallback') ?? [], true)) {
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
     * Keep only the destination that matches the chosen link type.
     *
     * The detail form always submits PageID, Link and File together (hidden fields
     * are still posted). Without clearing the others here, switching to "external"
     * while a page is still selected would hit the old "PageID wins" rule and wipe
     * the URL on save.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        match ($this->resolveWriteLinkType()) {
            'external' => $this->clearNonExternalDestination(),
            'file' => $this->clearNonFileDestination(),
            default => $this->clearNonInternalDestination(),
        };

        if ($this->Anchor) {
            // strip out any leading #s
            $this->Anchor = preg_replace('/^#/', '', $this->Anchor);
        }
    }

    /**
     * Link type for this write: favour the value posted by the CMS form when present.
     */
    private function resolveWriteLinkType(): string
    {
        $posted = $this->getField('LinkType');

        if (!is_string($posted) || $posted === '') {
            $controller = Controller::curr();
            $request = $controller ? $controller->getRequest() : null;
            $posted = $request ? (string) $request->requestVar('LinkType') : '';
        }

        if (in_array($posted, ['internal', 'external', 'file'], true)) {
            return $posted;
        }

        return $this->getLinkType();
    }

    private function clearNonExternalDestination(): void
    {
        $this->PageID = 0;
        $this->FileID = 0;
    }

    private function clearNonFileDestination(): void
    {
        $this->PageID = 0;
        $this->Link = null;
    }

    private function clearNonInternalDestination(): void
    {
        $this->Link = null;
        $this->FileID = 0;
    }


    public function getLinkType(): string
    {
        // Read raw columns — __get('Link') falls through to Page::Link() when empty.
        if ((int) $this->getField('FileID') > 0) {
            $type = 'file';
        } elseif ((int) $this->getField('PageID') > 0) {
            $type = 'internal';
        } elseif (($link = (string) $this->getField('Link')) !== '' && $link !== '/') {
            $type = 'external';
        } else {
            $type = 'internal';
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
            // Use getField() — with Link in $no_page_fallback, a blank destination
            // must stay blank rather than falling through to a page URL.
            $link = $this->getField('Link');
        }

        $link = (string) ($link ?? '');

        if ($this->Anchor) {
            $link .= '#' . $this->Anchor;
        }

        $this->extend('updateURL', $link);

        return (string) ($link ?? '');
    }


    public function getAbsoluteURL(): string
    {
        if ($this->PageID) {
            $link = (string) ($this->Page()->AbsoluteLink() ?? '');

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
