<?php

class MenuControllerExtension extends Extension
{

	public function MenuSet($name)
	{
		$menuSet = DataObject::get_one('MenuSet', "`MenuSet`.`Name` = '" . str_replace('-', ' ', $name) . "'");
		$sort = class_exists('DataObjectManager') ? '`SortOrder` ASC' : '`Sort` ASC';

		return ($menuSet instanceof MenuSet) ? $menuSet->MenuItems(null, $sort) : false;
	}

	/**
	 * Helper function to get the second level menu
	 *
	 * The second level menuset should be named the same as
	 * the "Title" of its parent link (Page)
	 *
	 * @return DataObjectSet
	 */
	public function MenuSetChildren()
	{
		$result = $this->MenuSet($this->getRootTitle());

		return $result;
	}

	private function getRootTitle()
	{
		$page = $this->owner;

		while ($page->ParentID) {
			$page = $page->Parent();
		}

		return $page->Title;
	}

}