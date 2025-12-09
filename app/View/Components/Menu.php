<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Route as FacadesRoute;
use Illuminate\View\Component;
use Illuminate\Support\Str;

class Menu extends Component
{
  /**
   * Create a new component instance.
   *
   * @return void
   */
  public function __construct(
    private string $label,
    private string $icon = '',
    private string $routeName = '',
    private ?array $routeParams = [],
    private string $labelBadge = '',
    private bool $hasSub = false,
    private string $active = '',
  ) {
    //
  }

  /**
   * Get the view / contents that represent the component.
   *
   * @return \Illuminate\Contracts\View\View|\Closure|string
   */
  public function render()
  {
    $url = ($this->hasSub) ? 'javascript:void(0);' : route($this->routeName, $this->routeParams ?? []);
    $isActive = $this->active ?: (FacadesRoute::is(str_replace('index','*',$this->routeName)) ? 'active' : '');
    if($isActive && $this->hasSub) $isActive .= ' open';

    $labelBadgeId = $this->labelBadge ? $this->labelBadge : 'label-badge-'.Str::slug($this->label);
    return view('components.menu', [
      'label' => $this->label,
      'icon' => $this->icon,
      'url' => $url,
      'labelBadge' => $this->labelBadge,
      'hasSub' => $this->hasSub,
      'labelBadgeId' => $labelBadgeId,
      'isActive' => $isActive
    ]);
  }
}
