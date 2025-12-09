<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme" style="background-color: #DDEBF7 !important;)">

  <div class="app-brand demo mx-0 px-0">
    <a href="#" class="app-brand-link">
      <a href="#" class="app-brand-link">
        <span class="app-brand-logo demo"><img src="{{ asset('tktw/logo-avian.png') }}" alt="Logo" class="mx-4" width="10%"></span>
      </a>

      <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
        <i class="bx bx-chevron-left bx-sm align-middle"></i>
      </a>
  </div>
  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    {{-- <li class="menu-header small text-uppercase"><span class="menu-header-text">Master</span></li> --}}
    {{-- @can('viewAny', App\Models\MasterRankCustomer::class)
      <x-menu label="Rank Customer" icon="bxs-file-archive" route-name="masterRankCustomer.index" />
    @endcan --}}

    <li class="menu-header small text-uppercase"><span class="menu-header-text">MAIN</span></li>
    <x-menu label="Dashboard" icon="bxs bx-home" route-name="dashboard" />
    <li class="menu-header small text-uppercase"><span class="menu-header-text">MASTERS</span></li>
    @can('viewAny', App\Models\User::class)
      <x-menu label="Master User" icon="bxs bx-user" route-name="masterUser.index" />
    @endcan
    @can('viewAny', App\Models\Menu::class)
      <x-menu label="Master Menu" icon="bxs bx-restaurant" route-name="masterMenu.index" />
    @endcan
    @can('viewAny', App\Models\LunchPickupWindow::class)
      <x-menu label="Set Jam Makan" icon="bxs bx-time-five" route-name="lunchWindow.index" />
    @endcan
    @can('viewAny', App\Models\Report::class)
      <x-menu label="Master Report" icon="bxs bx-line-chart" route-name="masterReport.index" />
    @endcan
  </ul>
  <h6 style="margin-right: 10px;margin-bottom: 1px">©{{ date('Y') }} v{{ config('app.version') }}</h6>
</aside>
