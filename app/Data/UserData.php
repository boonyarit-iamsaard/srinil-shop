<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\UserRole;
use Spatie\LaravelData\Data;

final class UserData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly UserRole $role,
    ) {}
}
