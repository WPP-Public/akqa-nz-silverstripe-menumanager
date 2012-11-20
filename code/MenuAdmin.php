<?php

class MenuAdmin extends ModelAdmin
{

	public static $managed_models = array(
		'MenuSet'
	);

	public static $url_segment = 'menu';
	public static $menu_title = 'Menu Management';
	public static $model_importers = array();

}
