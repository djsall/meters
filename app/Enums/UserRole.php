<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            UserRole::Admin => __('user.admin'),
            UserRole::User => ucfirst(__('user.label')),
        };
    }

    public function isAdmin(): bool
    {
        return match ($this) {
            UserRole::Admin => true,
            default => false,
        };
    }
}
