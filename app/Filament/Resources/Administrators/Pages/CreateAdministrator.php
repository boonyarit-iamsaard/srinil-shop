<?php

declare(strict_types=1);

namespace App\Filament\Resources\Administrators\Pages;

use App\Data\UserData;
use App\Enums\UserRole;
use App\Filament\Resources\Administrators\AdministratorResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAdministrator extends CreateRecord
{
    protected static string $resource = AdministratorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userData = UserData::from([...$data, 'role' => UserRole::Admin]);

        return [
            ...$userData->toArray(),
            'password' => $data['password'],
        ];
    }
}
