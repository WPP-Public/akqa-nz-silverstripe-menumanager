<?php

Object::add_extension('ContentController', 'MenuControllerExtension');

if (class_exists('DataObjectManager')) {

    SortableDataObject::add_sortable_classes(array(
        'MenuItem'
    ));

}
