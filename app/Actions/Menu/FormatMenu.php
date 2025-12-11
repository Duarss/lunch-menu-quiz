<?php

namespace App\Actions\Menu;

use App\Models\Menu;

class FormatMenu extends MenuAction
{
    // Note: formatMenu formats a Menu model into an array representation
    public function __invoke(Menu $menu): array
    {
        return $this->formatMenu($menu);
    }
}
