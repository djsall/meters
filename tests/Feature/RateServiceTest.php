<?php

use App\Models\Meter;
use App\Models\Reading;
use App\Services\RateService;
use Illuminate\Support\Carbon;

it('calculates the average daily rate across a missing gap', function () {
    // 1. Setup: Create a Meter
    $meter = Meter::factory()->create();

    // 2. Create "Anchor" Readings (60 units over 30 days = 2.0 per day)
    Reading::factory()->create([
        'meter_id' => $meter->id,
        'value' => 100,
        'date' => Carbon::now()->subDays(15),
    ]);

    Reading::factory()->create([
        'meter_id' => $meter->id,
        'value' => 160,
        'date' => Carbon::now()->addDays(15),
    ]);

    // 3. Initialize Service for a range inside that gap
    $service = new RateService($meter);

    // 4. Assert
    // Total change: 60. Total days: 30. Rate: 2.0
    expect(
        $service->getEstimatedRate(
            Carbon::now()->subDays(2),
            Carbon::now()->addDays(2)
        )
    )->toBe(2.0);
});

it('returns null when insufficient readings exist', function () {
    $meter = Meter::factory()->create();

    // Only one reading
    Reading::factory()->create(['meter_id' => $meter->id]);

    $service = new RateService($meter);

    expect(
        $service->getEstimatedRate(
            Carbon::now(),
            Carbon::now()->addDay()
        )
    )->toBeNull();
});

it('handles same-day readings to prevent division by zero', function () {
    $meter = Meter::factory()->create();
    $date = Carbon::now();

    Reading::factory()->count(2)->create([
        'meter_id' => $meter->id,
        'value' => 100,
        'date' => $date,
    ]);

    $service = new RateService($meter);

    // Should return 0 rather than crashing
    expect($service->getEstimatedRate($date, $date))->toBeNull();
});
