<?php

namespace App\Console\Commands;

use App\Models\Meter;
use App\Notifications\Meter\ForgotToRead;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class NotifyMissedReading extends Command
{
    protected $signature = 'app:notify-missed-reading';

    public function handle()
    {
        Meter::query()
            ->with(['user', 'sharedWith', 'user.notifications', 'sharedWith.notifications'])
            ->whereDoesntHave('user.notifications', function (Builder $query) {
                $query->whereDate('created_at', '>', today()->subMonth());
            })
            ->whereDoesntHave('sharedWith.notifications', function (Builder $query) {
                $query->whereDate('created_at', '>', today()->subMonth());
            })
            ->whereDoesntHave('readings', function (Builder $query) {
                $query->whereDate('date', '>', today()->subMonth());
            })
            ->get()
            ->each(function (Meter $meter) {
                $meter->user->notify(new ForgotToRead($meter));
                foreach ($meter->sharedWith as $sharedWith) {
                    $sharedWith->notify(new ForgotToRead($meter));
                }
            });
    }
}
