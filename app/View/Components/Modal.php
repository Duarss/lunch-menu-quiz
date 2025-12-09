<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Modal extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
      private string $id,
      private ?string $title = null,
      private string $size = 'md',
      private string $btnClose = 'Batal',
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
        return view('components.modal',[
          'id' => $this->id,
          'title' => $this->title,
          'size' => $this->size,
          'btnClose' => $this->btnClose,
        ]);
    }
}
