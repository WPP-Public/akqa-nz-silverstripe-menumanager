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
     * @var array
     */
    private static $model_importers = array();

    /**
     * @param mixed $id
     * @param mixed $fields
     * @return ModelAdmin
     */
    public function getEditForm($id = null, $fields = null) {
        $form = parent::getEditForm($id, $fields);

        if (Config::inst()->get($this->modelClass, 'allow_sorting')) {
            $gridFieldName = $this->sanitiseClassName($this->modelClass);
            $gridField = $form->Fields()->fieldByName($gridFieldName);

            $gridField->getConfig()->addComponent(new GridFieldOrderableRows());
        }

        return $form;
    }
}
