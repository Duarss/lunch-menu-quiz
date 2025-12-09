<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Form extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
      private string $action = '',
      private string $method = 'get',
      private bool $sendFile = false,
      private bool $requiredNote = false
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
        return view('components.form',[
          'action' => $this->action,
          'method' => $this->method,
          'sendFile' => $this->sendFile,
          'requiredNote' => $this->requiredNote,
        ]);
    }
}
