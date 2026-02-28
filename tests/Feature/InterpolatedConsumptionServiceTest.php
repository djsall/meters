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

it('ensures the sum of daily consumption equals the total delta', function () {
    // Jan 1st: 1000
    // Jan 31st: 1310 (310 units over 30 days of growth)
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-01'), 'value' => 1000]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-31'), 'value' => 1300]);

    $start = Carbon::parse('2025-01-01');
    $end = Carbon::parse('2025-01-30'); // The range we want to sum

    $daily = $this->service->getDailyConsumption($start, $end);
    $totalSum = $daily->sum('consumption');

    // Total delta is 300. If your service reports 310 or 290, you have a boundary error.
    expect($totalSum)->toBe(300.0);
});

it('correctly attributes consumption on the exact day of a reading', function () {
    // Day 1: 100
    // Day 2: 110
    // Day 3: 130
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-01 00:00:00'), 'value' => 100]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-02 00:00:00'), 'value' => 110]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-03 00:00:00'), 'value' => 130]);

    $daily = $this->service->getDailyConsumption(Carbon::parse('2025-01-01'), Carbon::parse('2025-01-02'));

    // Jan 1 consumption should be (Value at Jan 2 00:00) - (Value at Jan 1 00:00) = 10
    expect($daily->firstWhere('day', Carbon::parse('2025-01-01'))['consumption'])->toBe(10.0);
});

it('calculates total consumption accurately for months of different lengths', function ($start, $end, $dailyRate, $expectedTotal) {
    // Setup: 1000 units at the start
    Reading::factory()->create([
        'meter_id' => $this->meter->id,
        'date' => Carbon::parse($start),
        'value' => 1000,
    ]);

    // Setup: Reading at the end based on a daily rate
    $days = Carbon::parse($start)->diffInDays(Carbon::parse($end));
    Reading::factory()->create([
        'meter_id' => $this->meter->id,
        'date' => Carbon::parse($end),
        'value' => 1000 + ($days * $dailyRate),
    ]);

    $total = $this->service->getTotalConsumption(Carbon::parse($start), Carbon::parse($end));

    expect($total)->toBe((float) $expectedTotal);
})->with([
    'February (28 days) @ 10/day' => ['2025-02-01', '2025-03-01', 10, 280.0],
    'July (31 days) @ 10/day' => ['2025-07-01', '2025-08-01', 10, 310.0],
    'Full Year @ 10/day' => ['2025-01-01', '2026-01-01', 10, 3650.0],
]);
