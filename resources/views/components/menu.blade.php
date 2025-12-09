<li class="menu-item {{ $isActive }}">
  <a href="{{ $url }}" class="menu-link @if($hasSub) menu-toggle @endif">
    @if($icon) <i class="menu-icon tf-icons bx {{ $icon }}"></i>@endif
    <div data-i18n="{{ $label }}">{{ $label }}</div>
    <div class="badge bg-secondary rounded-pill ms-auto" id="{{ $labelBadgeId }}"></div>
  </a>
  @if($hasSub)
    <ul class="menu-sub">
      {!! $slot !!}
    </ul>
  @endif
