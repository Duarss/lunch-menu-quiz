<?php

namespace App\Services;

use App\Actions\User\BuildUserIndexData;
use App\Actions\User\ChangeUserPassword;
use App\Actions\User\CreateKaryawanUser;
use App\Actions\User\DeleteUser;
use App\Actions\User\ImportKaryawanUsersFromRows;
use App\Actions\User\NormalizeUserPayload;
use App\Actions\User\ResetUserPassword;
use App\Actions\User\UpdateKaryawanUser;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(
        private BuildUserIndexData $buildUserIndexData,
        private NormalizeUserPayload $normalizeUserPayload,
        private CreateKaryawanUser $createKaryawanUser,
        private UpdateKaryawanUser $updateKaryawanUser,
        private DeleteUser $deleteUser,
        private ResetUserPassword $resetUserPassword,
        private ChangeUserPassword $changeUserPassword,
        private ImportKaryawanUsersFromRows $importKaryawanUsersFromRows
    ) {}

    public function getMasterIndexData(array $params = []): LengthAwarePaginator
    {
        return ($this->buildUserIndexData)($params);
    }

    public function fetch(array $request, bool $forUpdate = false): object
    {
        return ($this->normalizeUserPayload)($request, $forUpdate);
    }

    public function store(array $request): User
    {
        return ($this->createKaryawanUser)($request);
    }

    public function update(User $user, array $request): User
    {
        return ($this->updateKaryawanUser)($user, $request);
    }

    public function delete(User $user): void
    {
        ($this->deleteUser)($user);
    }

    public function resetPassword(User $user): User
    {
        return ($this->resetUserPassword)($user);
    }

    public function createKaryawan(array $request): User
    {
        return $this->store($request);
    }

    public function updateKaryawan(string $username, array $request): User
    {
        $user = User::where('username', $username)->firstOrFail();

        return $this->update($user, $request);
    }

    public function deleteKaryawan(string $username): void
    {
        $user = User::where('username', $username)->firstOrFail();

        $this->delete($user);
    }

    public function changeOwnPassword(User $user, string $newPassword): User
    {
        return ($this->changeUserPassword)($user, $newPassword);
    }

    public function importFromRows(array $rows): array
    {
        return ($this->importKaryawanUsersFromRows)($rows);
    }
}
