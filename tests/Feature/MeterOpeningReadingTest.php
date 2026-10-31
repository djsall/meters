<?php

use App\Models\Meter;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->old = Meter::factory()->create();
});

it('records the opening reading on the installation day of a replacement meter', function () {
    $meter = Meter::factory()->create(['previous_meter_id' => $this->old->id, 'installed_at' => '2025-03-01']);

    $meter->recordOpeningReading(12.5);

    $reading = $meter->readings()->sole();

    expect($reading->value)->toBe(12.5)
        ->and($reading->date->equalTo(Carbon::parse('2025-03-01')))->toBeTrue();
});

it('does not record an opening reading when one already exists on the installation day', function () {
    $meter = Meter::factory()->create(['previous_meter_id' => $this->old->id, 'installed_at' => '2025-03-01']);
    Reading::factory()->create(['meter_id' => $meter->id, 'date' => Carbon::parse('2025-03-01 10:00'), 'value' => 3]);

    $meter->recordOpeningReading(0);

    expect($meter->readings()->count())->toBe(1);
});

it('does not record an opening reading for meters that replaced nothing', function () {
    $meter = Meter::factory()->create(['installed_at' => '2025-03-01']);

    $meter->recordOpeningReading(0);

    expect($meter->readings()->exists())->toBeFalse();
});
