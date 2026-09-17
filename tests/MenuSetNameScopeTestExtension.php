<?php

namespace Heyday\MenuManager\Test;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;

/**
 * Stands in for the subsite extension, which scopes names to a subsite.
 */
class MenuSetNameScopeTestExtension extends Extension implements TestOnly
{
    public function updateMenuSetsSharingNames(DataList &$list): void
    {
        $list = $list->filter('ID', -1);
    }
}
