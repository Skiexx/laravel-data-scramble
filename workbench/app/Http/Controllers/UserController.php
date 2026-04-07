<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Resources\Json\JsonResource;
use Skiexx\LaravelDataScramble\Attributes\ResponseData;
use Workbench\App\Data\CreateUserData;
use Workbench\App\Data\UpdateUserData;
use Workbench\App\Data\UserData;
use Workbench\App\Data\UserFilterData;
use Workbench\App\Data\UserProfileData;

class UserController
{
    /** Список пользователей с фильтрацией и пагинацией. */
    #[ResponseData(UserData::class, paginated: true)]
    public function index(UserFilterData $filter): JsonResource
    {
        return JsonResource::collection([]);
    }

    /** Получить профиль пользователя с вложенным адресом. */
    public function show(int $id): UserProfileData
    {
        return UserProfileData::from([
            'id' => $id,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'address' => ['city' => 'Moscow', 'street' => 'Main St', 'zip' => '101000'],
            'role' => 'admin',
        ]);
    }

    /** Создать нового пользователя. */
    #[ResponseData(UserData::class, status: 201)]
    public function store(CreateUserData $data): JsonResource
    {
        return new JsonResource([]);
    }

    /** Обновить пользователя. */
    #[ResponseData(UserData::class)]
    public function update(UpdateUserData $data): JsonResource
    {
        return new JsonResource([]);
    }

    /** Удалить пользователя. */
    public function destroy(int $id): JsonResource
    {
        return new JsonResource(['deleted' => true]);
    }
}
