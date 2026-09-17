<?php

namespace Heyday\MenuManager;

use SilverStripe\Versioned\Versioned;

/**
 * Gives a record a first version if it does not have one.
 *
 * Rows written before the Versioned extension was applied have Version 0 and no history.
 * Publishing compares draft and live version numbers, so 0 against 0 reads as "nothing changed"
 * and the publish silently does nothing. Writing the record once creates version 1 and makes it
 * publishable.
 */
trait EnsuresVersion
{
    public function ensureVersionExists(): bool
    {
        if (!$this->hasExtension(Versioned::class)) {
            return false;
        }

        // getField() rather than the property, so a magic getter cannot answer for it
        if ((int) $this->getField('Version') > 0) {
            return false;
        }

        $this->forceChange();
        $this->write();

        return true;
    }
}
