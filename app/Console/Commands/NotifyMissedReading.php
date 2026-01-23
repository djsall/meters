<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Meter\ForgotToRead;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class NotifyMissedReading extends Command
{
    protected $signature = 'app:notify-missed-reading';

    public function handle(): void
    {
        User::query()
            ->with(['overdueMeters', 'overdueSharedMeters'])
            ->hasOverdueMeters()
            ->where(function (Builder $query): Builder {
                return $query
                    ->whereDate('last_notified', '<', today()->subMonth())
                    ->orWhereNull('last_notified');
            })
            ->get()
            ->each(function (User $user) {
                foreach ($user->overdueMeters as $meter) {
                    $user->notify(new ForgotToRead($meter));
                    $user->touchQuietly('last_notified');
                }

                foreach ($user->overdueSharedMeters as $meter) {
                    $user->notify(new ForgotToRead($meter));
                    $user->touchQuietly('last_notified');
                }
            });
    }
}
