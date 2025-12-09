<div class="d-flex justify-content-center">
    @can('update', $user)
        <x-button data-username="{{ $user->username }}" title="Edit" icon="bx-edit" class="btn-outline-warning btn-sm btn-edit" data-bs-toggle="tooltip" data-bs-placement="bottom"></x-button>
    @endcan
    @can('delete', $user)
        <x-button data-username="{{ $user->username }}" title="Delete" icon="bx-x" class="btn-outline-danger btn-sm btn-delete" data-bs-toggle="tooltip" data-bs-placement="bottom"></x-button>
    @endcan
</div>