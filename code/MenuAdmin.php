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
        'MenuSet'
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
     * @var array
     */
    private static $model_importers = [];
}
