<?php

/**
 * Class MenuSet
 */
class MenuSet extends DataObject implements PermissionProvider
{
    /**
     * @var array
     */
    private static $db = array(
        'Name' => 'Varchar(255)'
    );
    /**
     * @var array
     */
    private static $has_many = array(
        'MenuItems' => 'MenuItem'
    );
    /**
     * @var array
     */
    private static $searchable_fields = array(
        'Name'
    );
    /**
     * @var array
     */
    private static $summary_fields = array(
        'Name'
    );
    /**
     * @return array
     */
    public function providePermissions()
    {
        return array(
            'MANAGE_MENU_SETS' => 'Manage Menu Sets',
        );
    }
    /**
     * @param null $member
     * @return boolean
     */
    public function canCreate($member = null)
    {
        return Permission::check('MANAGE_MENU_SETS');
    }
    /**
     * @param null $member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        return Permission::check('MANAGE_MENU_SETS');
    }
    /**
     * @param null $member
     * @return boolean
     */
    public function canEdit($member = null)
    {
        return Permission::check('MANAGE_MENU_SETS');
    }
    /**
     * @param null $member
     * @return boolean
     */
    public function canView($member = null)
    {
        return Permission::check('MANAGE_MENU_SETS');
    }
    /**
     * @return mixed
     */
    public function Children()
    {
        return $this->MenuItems();
    }
    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = new FieldList();

        if ($this->ID != null) {
            $fields->removeByName('Name');

            $fields->push(
                $menuItems = new GridField(
                    'MenuItems',
                    'Menu Items',
                    $this->MenuItems(),
                    $config = GridFieldConfig_RelationEditor::create()
                )
            );

            $config->addComponent(new GridFieldOrderableRows());

        } else {
            $fields->push(new TextField('Name', 'Name (this field can\'t be changed once set)'));
        }

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }
    /**
     *
     */
    public function onBeforeDelete()
    {
        $menuItems = $this->MenuItems();

        if ($menuItems instanceof DataList && count($menuItems) > 0) {
            foreach ($menuItems as $menuItem) {
                $menuItem->delete();
            }
        }

        parent::onBeforeDelete();
    }

}
