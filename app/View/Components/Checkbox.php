<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Checkbox extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
      private string $id,
      private string $name,
      private string $label = '',
      private string $value = '',
      private int $checked = 0,
      private bool $isSwitch = false,
      private bool $isInline = false,
      private string $containerClass = 'mb-0',
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
        return view('components.checkbox',[
          'id' => $this->id,
          'name' => $this->name,
          'label' => $this->label,
          'value' => $this->value,
          'checked' => $this->checked,
          'isSwitch' => $this->isSwitch,
          'isInline' => $this->isInline,
          'containerClass' => $this->containerClass
        ]);
    }
}
