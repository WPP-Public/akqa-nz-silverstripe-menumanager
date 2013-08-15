<?php

if (class_exists('ContentController')) {
    Object::add_extension('ContentController', 'MenuControllerExtension');
}