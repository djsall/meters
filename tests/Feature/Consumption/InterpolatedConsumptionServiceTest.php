<?php

use App\Models\Meter;
use App\Models\Reading;
use App\Services\Consumption\InterpolatedConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->meter = Meter::factory()->create();

    $this->service = new InterpolatedConsumptionService($this->meter);
});

it('spreads consumption evenly between two readings', function () {
    // 100 units over 10 days (10 per day)
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-01'), 'value' => 100]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-11'), 'value' => 200]);

    $total = $this->service->getTotalConsumption(Carbon::parse('2025-01-03'), Carbon::parse('2025-01-08'));

    expect($total)->toEqualWithDelta(50, 0.001);
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

it('returns null for periods without reading coverage', function () {
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-02-01'), 'value' => 500]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-03-01'), 'value' => 780]);

    $monthly = $this->service->getMonthlyConsumption(Carbon::parse('2025-01-01'), Carbon::parse('2025-04-30'));

    expect($monthly->pluck('consumption')->all())->toBe([null, 280.0, null, null]);
});

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

it('uses only the first reading of a day', function () {
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-01 08:00'), 'value' => 100]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-01 20:00'), 'value' => 104]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-11 09:00'), 'value' => 200]);

    $daily = $this->service->getDailyConsumption(Carbon::parse('2025-01-01'), Carbon::parse('2025-01-10'));

    expect($daily->pluck('consumption')->take(10)->unique()->all())->toBe([10.0]);
});

it('counts whole days regardless of the time of reading or DST', function () {
    // Mar 30 2025 is only 23 hours long in Europe/Budapest
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-03-29 21:00'), 'value' => 100]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-03-31 07:00'), 'value' => 120]);

    $daily = $this->service->getDailyConsumption(Carbon::parse('2025-03-01'), Carbon::parse('2025-03-30'))
        ->keyBy(fn (array $row): string => $row['day']->toDateString());

    expect($daily['2025-03-29']['consumption'])->toBe(10.0)
        ->and($daily['2025-03-30']['consumption'])->toBe(10.0)
        ->and($this->service->getAverageDailyConsumption(Carbon::parse('2025-03-29'), Carbon::parse('2025-03-31')))->toBe(10.0);
});

it('averages monthly consumption over the covered months only', function () {
    // 10/day from Jan 1 until Sep 1, after which the meter was retired
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-01'), 'value' => 0]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-09-01'), 'value' => 2430]);

    $average = $this->service->getAverageMonthlyConsumption(Carbon::parse('2025-01-01'), Carbon::parse('2025-11-01'));

    expect($average)->toEqualWithDelta(2430 / 8, 0.001);
});

it('counts partially covered months by the share of days covered', function () {
    // 10/day for the first 15 days of January
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-01'), 'value' => 0]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-16'), 'value' => 150]);

    $average = $this->service->getAverageMonthlyConsumption(Carbon::parse('2025-01-01'), Carbon::parse('2025-02-01'));

    expect($average)->toEqualWithDelta(310, 0.001)
        ->and($this->service->getAverageMonthlyConsumption(Carbon::parse('2025-03-01'), Carbon::parse('2025-04-01')))->toBeNull();
});

it('rounds daily consumption to two and monthly consumption to one decimal', function () {
    // 1 unit over 3 days
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-01'), 'value' => 0]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-01-04'), 'value' => 1]);
    Reading::factory()->create(['meter_id' => $this->meter->id, 'date' => Carbon::parse('2025-02-04'), 'value' => 2]);

    expect($this->service->getDailyConsumption(Carbon::parse('2025-01-01'), Carbon::parse('2025-01-01'))->first()['consumption'])->toBe(0.33)
        ->and($this->service->getMonthlyConsumption(Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'))->first()['consumption'])->toBe(1.9);
});
