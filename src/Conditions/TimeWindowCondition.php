<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Conditions;

use Illuminate\Support\Carbon;

final class TimeWindowCondition extends Condition
{
    public function type(): string
    {
        return 'time_window';
    }

    public function label(): string
    {
        return __('role-matrix::conditions.time_window');
    }

    public function schema(): array
    {
        return [
            ['name' => 'days', 'type' => 'text', 'label' => __('role-matrix::conditions.days'), 'default' => '1,2,3,4,5'],
            ['name' => 'start', 'type' => 'text', 'label' => __('role-matrix::conditions.start_time'), 'default' => '09:00'],
            ['name' => 'end', 'type' => 'text', 'label' => __('role-matrix::conditions.end_time'), 'default' => '17:00'],
            ['name' => 'start_date', 'type' => 'text', 'label' => __('role-matrix::conditions.start_date'), 'default' => ''],
            ['name' => 'end_date', 'type' => 'text', 'label' => __('role-matrix::conditions.end_date'), 'default' => ''],
            ['name' => 'timezone', 'type' => 'text', 'label' => __('role-matrix::conditions.timezone'), 'default' => ''],
        ];
    }

    public function summary(array $config): string
    {
        $start = $this->string($config, 'start');
        $end = $this->string($config, 'end');

        if ($start !== '' && $end !== '') {
            return __('role-matrix::conditions.time_summary', ['start' => $start, 'end' => $end]);
        }

        return __('role-matrix::conditions.time_window');
    }

    public function evaluate(ConditionContext $context, array $config): bool
    {
        $timezone = $this->string($config, 'timezone', (string) config('app.timezone'));
        $now = Carbon::now($timezone !== '' ? $timezone : null);
        $days = $this->list($config, 'days');

        if ($days !== [] && ! in_array((string) $now->dayOfWeek, $days, true)) {
            return false;
        }

        $startDate = $this->string($config, 'start_date');
        $endDate = $this->string($config, 'end_date');

        if ($startDate !== '' && $now->lt(Carbon::parse($startDate, $timezone !== '' ? $timezone : null)->startOfDay())) {
            return false;
        }

        if ($endDate !== '' && $now->gt(Carbon::parse($endDate, $timezone !== '' ? $timezone : null)->endOfDay())) {
            return false;
        }

        $start = $this->string($config, 'start');
        $end = $this->string($config, 'end');

        if ($start === '' || $end === '') {
            return true;
        }

        $minutes = ($now->hour * 60) + $now->minute;
        $startMinutes = $this->minutes($start);
        $endMinutes = $this->minutes($end);

        if ($startMinutes <= $endMinutes) {
            return $minutes >= $startMinutes && $minutes <= $endMinutes;
        }

        return $minutes >= $startMinutes || $minutes <= $endMinutes;
    }

    private function minutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }
}
