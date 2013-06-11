<?php

/**
 * Class MenuAdmin
 */
class MenuAdmin extends ModelAdmin
{
    /**
     * @var array
     */
    public static $managed_models = array(
        'MenuSet'
    );
    /**
     * @var string
     */
    public static $url_segment = 'menu';
    /**
     * @var string
     */
    public static $menu_title = 'Menu Management';
    /**
     * @var array
     */
    public static $model_importers = array();
}
