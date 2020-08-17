<?php

namespace Heyday\MenuManager;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;

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

    /**
     * @var bool
     */
    private static $enable_cms_create = true;

    /**
     * Adjust the CMS's ability to create MenuSets
     *
     * {@inheritDoc}
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        /** @var GridField $gridField */
        $gridField = $form->Fields()->dataFieldByName($this->sanitiseClassName(MenuSet::class));

        if ($gridField) {
            if (!$this->config()->get('enable_cms_create')) {
                $gridField->getConfig()
                    ->removeComponentsByType([
                        GridFieldAddNewButton::class,
                        GridFieldImportButton::class,
                    ]);
            }
        }

        return $form;
    }
}
