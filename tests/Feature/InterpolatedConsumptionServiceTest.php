<?php

use App\Models\Meter;
use App\Models\Reading;
use App\Services\InterpolatedConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->meter = Meter::factory()->create();

    $this->service = new InterpolatedConsumptionService($this->meter);
});

it('calculates exact interpolation between two readings', function () {
    // Setup: Reading of 100 on Jan 1st, 200 on Jan 11th (10 units per day)
    $readings = collect([
        new Reading(['date' => Carbon::parse('2025-01-01'), 'value' => 100]),
        new Reading(['date' => Carbon::parse('2025-01-11'), 'value' => 200]),
    ]);

    // Test: What was the reading on Jan 6th? (Halfway point)
    $target = Carbon::parse('2025-01-06');
    $result = $this->service->interpolate($readings, $target);

    expect($result)->toBe(150.0);
});

it('calculates average daily consumption correctly', function () {

    // 2. Create "sandwich" readings
    // May 31: 1000 units
    Reading::factory()->create([
        'meter_id' => $this->meter->id,
        'date' => Carbon::parse('2025-05-31'),
        'value' => 1000,
    ]);

    // July 1: 1310 units (310 units over 31 days = 10 units/day)
    Reading::factory()->create([
        'meter_id' => $this->meter->id,
        'date' => Carbon::parse('2025-07-01'),
        'value' => 1310,
    ]);

    $start = Carbon::parse('2025-06-01');
    $end = Carbon::parse('2025-06-30');

    $avg = $this->service->getAverageDailyConsumption($start, $end);

    // Calculation check:
    // June 1 interpolated: 1010
    // June 30 interpolated: 1300
    // Delta: 290. Days: 29. Result: 10.
    expect($avg)->toEqualWithDelta(10, 0.1);
});

it('handles edge cases in interpolation', function ($targetDate, $expectedValue) {
    $readings = collect([
        new Reading(['date' => Carbon::parse('2025-01-10'), 'value' => 500]),
        new Reading(['date' => Carbon::parse('2025-01-20'), 'value' => 1000]),
    ]);

    $result = $this->service->interpolate($readings, Carbon::parse($targetDate));

    expect($result)->toBe($expectedValue);
})->with([
    'date before first reading' => ['2025-01-01', null],
    'date exactly on a reading' => ['2025-01-10', 500.0],
    'date after last reading' => ['2025-01-25', 1000.0],
    'mid-point calculation' => ['2025-01-15', 750.0],
]);
