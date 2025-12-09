<?php

namespace App\View\Components;

use Illuminate\View\Component;

class CardData extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
      public string $id,
      public string $title,
      public string $subtitle,
      public string $icon,
      public string $unit,
      public string $colour = 'primary',
      public string $value = '-'
    )
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.card-data');
    }
}
