<?php

declare(strict_types=1);

namespace App\Filament\Resources\Administrators\Pages;

use App\Data\UserData;
use App\Enums\UserRole;
use App\Filament\Resources\Administrators\AdministratorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditAdministrator extends EditRecord
{
    protected static string $resource = AdministratorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $userData = UserData::from([...$data, 'role' => UserRole::Admin]);

        $result = $userData->toArray();

        if (filled($data['password'] ?? null)) {
            $result['password'] = $data['password'];
        }

        return $result;
    }
}
