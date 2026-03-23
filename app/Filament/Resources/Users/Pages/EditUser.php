<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Data\UserData;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

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
        $userData = UserData::from($data);

        $result = $userData->toArray();

        if (filled($data['password'] ?? null)) {
            $result['password'] = $data['password'];
        }

        return $result;
    }
}
