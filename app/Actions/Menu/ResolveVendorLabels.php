<?php

namespace App\Actions\Menu;

class ResolveVendorLabels extends MenuAction
{
    // Note: __invoke retrieves display labels for all vendors
    public function __invoke(): array
    {
        return $this->vendorLabels();
    }
}
