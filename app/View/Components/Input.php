<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Input extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
      private string $id,
      private string $name,
      private ?string $required = null,
      private ?string $label = null,
      private string $type = 'text',
      private ?string $value = null,
      private ?string $placeholder = null,
      private ?string $min = null,
      private ?string $max = null,
      private string $containerClass = 'mb-0',
      private string $help = '',
      private bool $disabled = false,
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
        return view('components.input',[
          'id' => $this->id,
          'name' => $this->name,
          'required' => $this->required,
          'label' => $this->label,
          'type' => $this->type,
          'value' => $this->value,
          'placeholder' => $this->placeholder,
          'min' => $this->min,
          'max' => $this->max,
          'containerClass' => $this->containerClass,
          'help' => $this->help,
          'disabled' => $this->disabled,
      ]);
    }
}
