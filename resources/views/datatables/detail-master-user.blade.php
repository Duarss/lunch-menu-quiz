<div class="d-flex justify-content-center">
    @can('viewDetails', $user)
        <x-button data-username="{{ $user->username }}" title="Details" icon="bx-detail" class="btn-outline-secondary btn-sm btn-details" data-bs-toggle="tooltip" data-bs-placement="bottom"></x-button>
    @endcan
</div>