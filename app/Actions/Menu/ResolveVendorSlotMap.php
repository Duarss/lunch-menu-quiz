<?php

namespace App\Actions\Menu;

class ResolveVendorSlotMap extends MenuAction
{
    // Note: __invoke retrieves the slot mapping for a given catering vendor
    public function __invoke(string $catering): array
    {
        return $this->vendorSlotMap($catering);
    }
}
