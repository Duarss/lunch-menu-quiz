<?php

namespace App\Actions\Menu;

class ResolveVendorSlotMap extends MenuAction
{
    public function __invoke(string $catering): array
    {
        return $this->vendorSlotMap($catering);
    }
}
