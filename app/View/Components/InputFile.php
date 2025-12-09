<?php

namespace App\View\Components;

use Illuminate\View\Component;

class InputFile extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
      public string $id,
      public string $name,
      public string $label,
      public string $formId,
      public string $accepted = "image/*",
      public int $maxFiles = 1,
      public bool $required = false
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
        return view('components.input-file');
    }
}
