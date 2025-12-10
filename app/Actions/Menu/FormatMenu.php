<?php

namespace App\Actions\Menu;

use App\Models\Menu;

class FormatMenu extends MenuAction
{
    public function __invoke(Menu $menu): array
    {
        return $this->formatMenu($menu);
    }
}
