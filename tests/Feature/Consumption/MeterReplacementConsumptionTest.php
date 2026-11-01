<?php

use App\Models\Meter;
use App\Models\Reading;
use App\Services\Consumption\InterpolatedConsumptionService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function reading(Meter $meter, string $date, float $value): void
{
    Reading::factory()->create(['meter_id' => $meter->id, 'date' => Carbon::parse($date), 'value' => $value]);
}

function monthly(Meter $meter, string $year = '2025'): array
{
    return (new InterpolatedConsumptionService($meter))
        ->getMonthlyConsumption(Carbon::parse("{$year}-01-01"), Carbon::parse("{$year}-12-31")->endOfDay())
        ->mapWithKeys(fn (array $row): array => [$row['month']->format('m') => $row])
        ->all();
}

beforeEach(function () {
    // Old meter: 10 units/day through January and February, swapped on Feb 28
    $this->old = Meter::factory()->create();
    reading($this->old, '2024-12-31', 1000);
    reading($this->old, '2025-02-28', 1590);

    $this->new = Meter::factory()->create([
        'previous_meter_id' => $this->old->id,
        'installed_at' => '2025-02-28',
    ]);
});

it('continues the consumption across a meter swap with an opening reading', function () {
    reading($this->new, '2025-02-28', 0);
    reading($this->new, '2025-03-31', 310);

    $months = monthly($this->new);

    expect($months['01']['consumption'])->toBe(310.0)
        ->and($months['02']['consumption'])->toBe(280.0)
        ->and($months['03']['consumption'])->toBe(310.0)
        ->and($months['03']['is_estimated'])->toBeFalse()
        ->and($months['04']['consumption'])->toBeNull();
});

it('leaves the gap unknown when the new meter has no opening reading', function () {
    reading($this->new, '2025-03-10', 100);
    reading($this->new, '2025-03-31', 310);

    $months = monthly($this->new);

    expect($months['02']['consumption'])->toBe(280.0)
        ->and($months['03']['consumption'])->toBe(210.0)
        ->and($months['03']['is_estimated'])->toBeTrue();
});

it('ignores readings of the old meter after the swap', function () {
    reading($this->old, '2025-05-01', 9999);
    reading($this->new, '2025-02-28', 0);
    reading($this->new, '2025-05-31', 920);

    $months = monthly($this->new);

    expect($months['03']['consumption'])->toBe(310.0)
        ->and($months['05']['consumption'])->toBe(310.0);
});

it('ignores readings of the new meter before its installation', function () {
    reading($this->new, '2025-02-01', 50000);
    reading($this->new, '2025-02-28', 0);
    reading($this->new, '2025-03-31', 310);

    expect(monthly($this->new)['02']['consumption'])->toBe(280.0);
});

it('calculates totals and averages across the swap', function () {
    reading($this->new, '2025-03-10', 100);
    reading($this->new, '2025-03-31', 310);

    $service = new InterpolatedConsumptionService($this->new);

    // 590 on the old meter + 210 on the new one; the 10-day gap is not counted
    expect($service->getTotalConsumption(Carbon::parse('2025-01-01'), Carbon::parse('2025-04-01')))->toEqualWithDelta(800, 0.001)
        ->and($service->getAverageDailyConsumption(Carbon::parse('2025-01-01'), Carbon::parse('2025-04-01')))->toEqualWithDelta(10, 0.001);
});

it('does not use the predecessor for ranges after the swap', function () {
    reading($this->new, '2025-02-28', 0);
    reading($this->new, '2025-03-31', 310);

    $total = (new InterpolatedConsumptionService($this->new))
        ->getTotalConsumption(Carbon::parse('2025-03-05'), Carbon::parse('2025-03-15'));

    expect($total)->toEqualWithDelta(100, 0.001);
});

it('handles the old -> new swap without a negative month', function () {
    $old = Meter::factory()->create();
    reading($old, '2025-08-23', 562);
    reading($old, '2025-09-01', 564);

    $new = Meter::factory()->create(['previous_meter_id' => $old->id, 'installed_at' => '2025-09-01']);
    reading($new, '2025-09-19', 4.2);
    reading($new, '2025-10-06', 8.6);
    reading($new, '2025-10-31 21:00', 15);

    $months = monthly($new);

    // Without an opening reading only Sep 19 onwards is known on the new meter
    expect($months['09']['consumption'])->toBe(3.1)
        ->and($months['09']['is_estimated'])->toBeTrue()
        ->and($months['10']['consumption'])->toBe(8.0)
        ->and($months['11']['consumption'])->toBeNull();

    reading($new, '2025-09-01', 0);

    expect(monthly($new)['09']['consumption'])->toBe(7.3);
});

it('includes the predecessor readings when readings are scoped to the tenant meter', function () {
    reading($this->new, '2025-02-28', 0);
    reading($this->new, '2025-03-31', 310);

    // Mimic the global scope Filament registers on Reading inside the tenant-aware panel
    Reading::addGlobalScope(
        Filament::getCurrentOrDefaultPanel()->getTenancyScopeName(),
        fn (Builder $query) => $query->where('meter_id', $this->new->id),
    );

    try {
        $months = monthly($this->new);
    } finally {
        Reading::clearBootedModels();
    }

    expect($months['01']['consumption'])->toBe(310.0)
        ->and($months['02']['consumption'])->toBe(280.0)
        ->and($months['03']['consumption'])->toBe(310.0);
});
