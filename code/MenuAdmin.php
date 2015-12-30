<?php

/**
 * Class MenuAdmin
 */
class MenuAdmin extends ModelAdmin
{
    /**
     * @var array
     */
    private static $managed_models = array(
        'MenuSet'
    );

    /**
     * @var string
     */
    private static $url_segment = 'menu';

    /**
     * @var string
     */
    private static $menu_title = 'Menu Management';

    /**
     * @var string
     */
	private static $menu_icon = "silverstripe-menumanager/images/menu.png";

	/**
     * @var array
     */
    private static $model_importers = array();
}
