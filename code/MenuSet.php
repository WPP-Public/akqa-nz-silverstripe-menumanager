<?php

class MenuSet extends DataObject
{

    public static $db = array(
        'Name' => 'Varchar(255)'
    );

    public static $has_many = array(
        'MenuItems' => 'MenuItem'
    );

    public static $searchable_fields = array(
        'Name'
    );

    public static $summary_fields = array(
        'Name'
    );

    public function Children()
    {
        return $this->MenuItems();
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        if ($this->ID != null) {
            $fields->removeByName('Name');
        }

        if (class_exists('DataObjectManager')) {
            $fields->removeByName('MenuItems');

            $fields->addFieldToTab('Root.Main', new DataObjectManager(
                $this,
                'MenuItems',
                'MenuItem',
                array('MenuTitle' => 'MenuTitle'),
                null,
                "MenuSetID = " . $this->ID
            ));
        }

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    public function onBeforeDelete()
    {
        $menuItems = $this->MenuItems();

        if ($menuItems instanceof DataObjectSet && $menuItems->TotalItems() > 0) {
            foreach ($menuItems as $menuItem) {
                $menuItem->delete();
            }
        }

        parent::onBeforeDelete();
    }

}
