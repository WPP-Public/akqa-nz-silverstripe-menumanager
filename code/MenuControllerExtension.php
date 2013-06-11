<?php

/**
 * Class MenuControllerExtension
 */
class MenuControllerExtension extends Extension
{
    /**
     * @param $name
     * @return bool
     */
    public function MenuSet($name)
    {
        return MenuSet::get()
            ->filter(
                array(
                    'Name' => $name
                )
            )->First();
    }
}
