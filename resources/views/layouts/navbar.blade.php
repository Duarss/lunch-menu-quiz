<nav class="layout-navbar container-xxxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme mt-1" id="layout-navbar">
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="bx bx-menu bx-sm"></i>
    </a>
  </div>
  <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <ol class="breadcrumb m-0">
      @foreach($breadcrumbs as $value)
        <li class="breadcrumb-item text-white h5 m-0
          @if($value == $breadcrumbs[count($breadcrumbs) - 1])
            active
          @endif
        ">{{ $value }}</li>
      @endforeach
    </ol>
    <ul class="navbar-nav flex-row align-items-center ms-auto">
      <li class="nav-item mx-3">
        <div class="text-center px-4 py-1 rounded border border-white">
          <h5 class="mb-0 text-white" >{{ (config('app.surname')) }}</h5>
        </div>
      </li>
      <!-- Notification -->
      {{-- <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1">
        <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bx bx-bell bx-xm text-white" id="notification-icon"></i>
          <span class="badge rounded-pill bg-danger badge-dot badge-notifications" id="notification-total"></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end py-0" style="width: 400px;">
          <li class="dropdown-menu-header border-bottom">
            <div class="dropdown-header d-flex align-items-center py-3">
              <h5 class="text-body mb-0 me-auto">Notifikasi</h5>
              <a href="javascript:void(0)" id="btn-mark-as-read-all" class="dropdown-notifications-all text-body" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Mark all as read" data-bs-original-title="Mark all as read"><i class="bx fs-4 bx-envelope-open"></i></a>
            </div>
          </li>
          <li class="dropdown-notifications-list" >
            <ul class="list-group list-group-flush overflow-auto" id="notification-container" style="max-height: 350px;">

            </ul>
            <div class="ps__rail-x" style="left: 0px; bottom: 0px;">
              <div class="ps__thumb-x" tabindex="0" style="left: 0px; width: 0px;"></div>
            </div>
            <div class="ps__rail-y" style="top: 0px; right: 0px; height: 480px;">
              <div class="ps__thumb-y" tabindex="0" style="top: 0px; height: 228px;"></div>
            </div>
          </li>
        </ul>
      </li> --}}
      <!--/ Notification -->
      <!-- User -->
      <li class="menu-item dropdown">
        <a href="#" class="menu-link nav-link dropdown-toggle text-white" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="true">
          @auth <span class="text-white">{{ auth()->user()->name }}</span> @endauth
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <a class="dropdown-item" href="javascript:void(0);">
              <div class="d-flex">
                <div class="flex-shrink-0 me-3">
                  <div class="avatar avatar-online"><img src="{{ asset('assets/img/avatars/user-default.png') }}" alt class="w-px-40 h-auto rounded-circle" /></div>
                </div>
                <div class="flex-grow-1">
                  @auth
                    <span class="fw-semibold d-block">{{ auth()->user()->name }}</span>
                    <small class="text-muted">{{ auth()->user()->utility_role }} {{ auth()->user()->area ? 'Area '.auth()->user()->area : '' }}</small>
                  @endauth
                </div>
              </div>
            </a>
          </li>
          <li><div class="dropdown-divider"></div></li>
          <li>
            <form action="{{ route('logout') }}" method="post" id="form-logout"><input type="hidden" name="_token" value="{{ csrf_token() }}"></form>
            <a class="dropdown-item" href="javascript:document.getElementById('form-logout').submit()"><i class="bx bx-power-off me-2"></i><span class="align-middle">Keluar</span></a>
          </li>
        </ul>
      </li>
      <!--/ User -->
    </ul>
  </div>
</nav>
