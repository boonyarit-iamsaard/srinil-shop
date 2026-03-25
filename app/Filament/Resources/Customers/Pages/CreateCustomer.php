<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Data\UserData;
use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userData = UserData::from([...$data, 'role' => UserRole::Customer]);

        return [
            ...$userData->toArray(),
            'password' => $data['password'],
        ];
    }
}
