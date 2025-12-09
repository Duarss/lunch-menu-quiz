@pushOnce('css')
<style>
  .text-primary{
    color: #0e593a !important;
  }
  .text-warning{
    color: #ffab00 !important;
  }
  .card.card-border-shadow-primary:after {
      border-bottom-color: #0e593a;
  }
  .card.card-border-shadow-warning:after {
      border-bottom-color: #ffab00;
  }
  .card[class*=card-border-shadow-]:after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-bottom-width: 2px;
    border-bottom-style: solid;
    border-radius: .375rem;
    transition: all .2s ease-in-out;
    z-index: 1;
  }
</style>
@endPushOnce

<div class="col-12 col-sm-6 col-xl-3">
  <div class="card card-border-shadow-{{ $colour }} h-100 p-3">
    <div class="d-flex align-items-center">
      <div class="avatar flex-shrink-0 me-3">
        <i class='bx bx-md text-{{ $colour }} {{ $icon }}'></i>
      </div>
      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
        <div class="me-2">
          <small class="d-block">{{ $title }}</small>
          <h6 class="fw-normal mb-0">{{ $subtitle }}</h6>
        </div>
        <div class="user-progress d-flex align-items-center gap-2">
          <h4 class="fw-normal mb-0"><span id="label-{{ $id }}" class="fw-bold text-{{ $colour }}">{{ $value }}</span></h4> <span class="text-muted">{{ $unit }}</span>
        </div>
      </div>
    </div>
  </div>
</div>
