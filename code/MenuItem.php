<?php

class MenuItem extends DataObject
{

    public static $db = array(
        'MenuTitle'   => 'Varchar(255)', // If you want to customise the MenuTitle use this field - leaving blank will use MenuTitle of associated Page
        'Link'        => 'Text',         // This field is used for external links (picking a page from the dropdown will overwrite this link)
        'Sort'        => 'Int',          // Sort order
        'IsNewWindow' => 'Boolean'       // Can be used as a check for adding target="_blank"
    );

    public static $has_one = array(
        'Page'    => 'SiteTree', // page the MenuItem refers to
        'MenuSet' => 'MenuSet'   // parent MenuSet
    );

    public static $searchable_fields = array(
        'MenuTitle',
        'Page.Title'
    );

    public static $summary_fields = array(
        'MenuTitle',
        'Sort',
        'Page.Title'
    );

    public function getCMSFields()
    {
        $fields = new FieldSet(
            new TabSet('Root',
                new Tab('Main')
            )
        );

        $fields->addFieldToTab('Root.Main', new TextField('MenuTitle', 'Menu Title'));
        $fields->addFieldToTab('Root.Main', new TextField('Link', 'Link'));

        $pages = DataObject::get('Page', '', 'Title ASC');
        $pages = $pages->toDropDownMap('ID', 'MenuTitle');
        $pages = array('' => '--- Select One ---') + $pages;

        $pageDropdown = new DropdownField('PageID', 'Page', $pages);

        $fields->addFieldToTab('Root.Main', $pageDropdown);

        if (class_exists('DataObjectManager')) {
            $fields->addFieldToTab('Root.Main', new TextField('Sort', 'Sort'));
        }

        $fields->addFieldToTab('Root.Main', new CheckboxField('IsNewWindow', 'Open in a new window?'));

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

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
     * @param  string $field
     * @return mixed
     */
    public function __get($field)
    {
        $default = parent::__get($field);

        if ($default) {
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

    /**
     * No idea what this is for
     *
     * @return string
     */
    public function CurrentInSection()
    {
        if ($this->Page()->isSection() && !$this->Overview) {
            return "current section";
        }
    }

    /**
     * This method attempts to find a MenuSet with the same Name as this MenuItems MenuTitle.
     * So if you want to nest MenuSets that would be the way to do it
     *
     * @return ComponentSet
     */
    public function MenuSetChildren()
    {
        $name    = str_replace('-', ' ', str_replace("'", "\'", $this->MenuTitle));
        $menuSet = DataObject::get_one('MenuSet', "`MenuSet`.`Name` = '" . $name . "'");
        $sort    = class_exists('DataObjectManager') ? '`SortOrder` ASC' : '`Sort` ASC';

        return ($menuSet instanceof MenuSet) ? $menuSet->MenuItems(null, $sort) : false;
    }

}
