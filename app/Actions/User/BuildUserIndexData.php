<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildUserIndexData extends UserAction
{
    // Note: __invoke builds paginated user index data based on search parameters
    public function __invoke(array $params = []): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', 'karyawan')
            ->orWhere('role', 'vendor');

        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($params['per_page'] ?? 15);

        return $query->orderBy('username')
            ->paginate($perPage)
            ->appends(['search' => $search]);
    }
}
