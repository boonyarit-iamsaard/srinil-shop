<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Data\UserData;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userData = UserData::from($data);

        return [
            ...$userData->toArray(),
            'password' => $data['password'],
        ];
    }
}
