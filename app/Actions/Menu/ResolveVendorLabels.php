<?php

namespace App\Actions\Menu;

class ResolveVendorLabels extends MenuAction
{
    public function __invoke(): array
    {
        return $this->vendorLabels();
    }
}
