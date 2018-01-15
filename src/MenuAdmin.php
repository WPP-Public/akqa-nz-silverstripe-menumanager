<?php

namespace Heyday\MenuManager;

use SilverStripe\Admin\ModelAdmin;

/**
 * Class MenuAdmin
 */
class MenuAdmin extends ModelAdmin
{
    /**
     * @var array
     */
    private static $managed_models = [
        'Heyday\MenuManager\MenuSet'
    ];

    /**
     * @var string
     */
    private static $url_segment = 'menu-manager';

    /**
     * @var string
     */
    private static $menu_title = 'Menu Management';
    
    /**
     * @var string
     */
    private static $menu_icon_class = 'font-icon-list';

    /**
     * @var array
     */
    private static $model_importers = [];
}
