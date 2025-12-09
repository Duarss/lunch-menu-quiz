<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Select extends Component
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
      private ?string $multiple = null,
      private ?string $placeholder = null,
      private array $options = [],
      private string $containerClass = "mb-0",
      private bool $select2 = false,
      private bool $noEmptyOption = false,
      private string $help = "",
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
        return view('components.select',[
          'id' => $this->id,
          'name' => $this->name,
          'required' => $this->required,
          'label' => $this->label,
          'placeholder' => $this->placeholder,
          'options' => $this->options,
          'multiple' => $this->multiple,
          'containerClass' => $this->containerClass,
          'noEmptyOption' => $this->noEmptyOption,
          'select2' => $this->select2,
          'help' => $this->help,
        ]);
    }
}
