<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Data\UserData;
use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

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
        $userData = UserData::from([...$data, 'role' => UserRole::Customer]);

        $result = $userData->toArray();

        if (filled($data['password'] ?? null)) {
            $result['password'] = $data['password'];
        }

        return $result;
    }
}
