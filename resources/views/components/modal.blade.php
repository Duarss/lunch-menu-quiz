<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-{{ $size }}" role="document">
      <div class="modal-content">
          <div class="modal-header">
              <h5 class="modal-title" id="title-{{ $id }}">{{ $title }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              {!! $slot !!}
          </div>
          <div class="modal-footer">
              <x-button class="btn btn-secondary" data-bs-dismiss="modal" label="{{ $btnClose }}"/>
              {!! $footer ?? '' !!}
          </div>
      </div>
  </div>
</div>
