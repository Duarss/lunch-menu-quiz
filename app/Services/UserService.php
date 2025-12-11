<?php

namespace App\Services;

use App\Actions\User\BuildUserIndexData;
use App\Actions\User\ChangeUserPassword;
use App\Actions\User\CreateKaryawanUser;
use App\Actions\User\DeleteUser;
use App\Actions\User\ImportKaryawanUsersFromRows;
use App\Actions\User\ResetUserPassword;
use App\Actions\User\UpdateKaryawanUser;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    // Note: __construct injects various user-related actions
    public function __construct(
        private BuildUserIndexData $buildUserIndexData,
        private CreateKaryawanUser $createKaryawanUser,
        private UpdateKaryawanUser $updateKaryawanUser,
        private DeleteUser $deleteUser,
        private ResetUserPassword $resetUserPassword,
        private ChangeUserPassword $changeUserPassword,
        private ImportKaryawanUsersFromRows $importKaryawanUsersFromRows
    ) {}

    // Note: getMasterIndexData retrieves the master index data for users with optional parameters
    public function getMasterIndexData(array $params = []): LengthAwarePaginator
    {
        return ($this->buildUserIndexData)($params);
    }

    // Note: store creates a new karyawan user with the provided request data
    public function store(array $request): User
    {
        return ($this->createKaryawanUser)($request);
    }

    // Note: update updates the specified user with the provided request data
    public function update(User $user, array $request): User
    {
        return ($this->updateKaryawanUser)($user, $request);
    }

    // Note: delete removes the specified user from the database
    public function delete(User $user): void
    {
        ($this->deleteUser)($user);
    }

    // Note: resetPassword resets the password for the specified user
    public function resetPassword(User $user): User
    {
        return ($this->resetUserPassword)($user);
    }

    // Note: changeOwnPassword allows a user to change their own password
    public function changeOwnPassword(User $user, string $newPassword): User
    {
        return ($this->changeUserPassword)($user, $newPassword);
    }

    // Note: importFromRows imports karyawan users from the provided rows of data
    public function importFromRows(array $rows): array
    {
        return ($this->importKaryawanUsersFromRows)($rows);
    }
}
