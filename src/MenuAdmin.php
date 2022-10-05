<?php

namespace Heyday\MenuManager;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class MenuAdmin
 */
class MenuAdmin extends ModelAdmin
{
    /**
     * @var array
     */
    private static $managed_models = [
        MenuSet::class,
    ];

    /**
     * @var string
     */
    private static $url_segment = 'menu-manager';

    /**
     * @var string
     */
    private static $menu_title = 'Menus';

    /**
     * @var string
     */
    private static $menu_icon_class = 'font-icon-link';

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

        if (Config::inst()->get($this->modelClass, 'allow_sorting')) {
            $gridFieldName = $this->sanitiseClassName($this->modelClass);
            $gridField = $form->Fields()->fieldByName($gridFieldName);

            $gridField->getConfig()->addComponent(new GridFieldOrderableRows());
        }


        return $form;
    }
}
