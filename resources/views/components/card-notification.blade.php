<li class="list-group-item list-group-item-action dropdown-notifications-item" id="{{ $id }}">
  <div class="d-flex">
    <div class="flex-grow-1">
      <h6 class="small mb-0">{{ $title }}</h6>
      <small class="mb-1 d-block text-body">{{ $message }}</small>
      <small class="text-muted">{{ $time }}</small>
    </div>
    <div class="flex-shrink-0 dropdown-notifications-actions d-flex align-items-center">
      <x-button class="btn-outline-danger btn-xs btn-mark-as-read" icon="bx-x" data-id="{{ $id }}" title="Tandai Sudah Dibaca" data-bs-toggle="tooltip" data-bs-placement="bottom"/>
    </div>
  </div>
</li>
