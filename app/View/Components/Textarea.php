<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Textarea extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
      private string $id,
      private string $name,
      private ?string $label = null,
      private ?string $required = null,
      private ?string $value = null,
      private string $rows = '3',
      private string $containerClass = 'mb-0'
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
        return view('components.textarea',[
          'id' => $this->id,
          'name' => $this->name,
          'label' => $this->label,
          'required' => $this->required,
          'value' => $this->value,
          'rows' => $this->rows,
          'containerClass' => $this->containerClass,
        ]);
    }
}
