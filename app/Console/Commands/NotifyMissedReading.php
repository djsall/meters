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
            ->whereDoesntHave('readings', function (Builder $query) {
                $query->whereDate('date', '>', today()->subMonth());
            })
            ->whereDoesntHave('user', function (Builder $query) {
                $query->whereDate('last_notified', '>', today()->subMonth());
            })->whereDoesntHave('sharedWith', function (Builder $query) {
                $query->whereDate('last_notified', '>', today()->subMonth());
            })
            ->get()
            ->each(function (Meter $meter) {
                $meter->user->notify(new ForgotToRead($meter));
                $meter->user->updateQuietly(['last_notified' => today()]);

                foreach ($meter->sharedWith as $sharedWith) {
                    $sharedWith->notify(new ForgotToRead($meter));
                    $sharedWith->updateQuietly(['last_notified' => today()]);
                }
            });
    }
}
