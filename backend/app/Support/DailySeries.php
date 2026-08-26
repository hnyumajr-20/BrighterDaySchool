<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailySeries
{
    /**
     * Build one entry per day for the last $days days (oldest first, today
     * last), so charts never have to guess at a missing date — every day
     * gets an entry, zero-filled where $rows has nothing for it.
     *
     * @param  Collection  $rows  Any rows with a date-like field.
     * @param  string  $dateKey  The field on each row holding its date.
     * @param  array  $zero  The shape (minus 'date') a day with no rows gets.
     * @param  callable  $reduce  (array $day, mixed $row): array — folds one row into that day's entry.
     * @return array<int, array>
     */
    public static function build(int $days, Collection $rows, string $dateKey, array $zero, callable $reduce): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $byDate = $rows->groupBy(function ($row) use ($dateKey) {
            $value = $row->{$dateKey};

            return $value instanceof Carbon ? $value->toDateString() : Carbon::parse($value)->toDateString();
        });

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $dateString = $start->copy()->addDays($i)->toDateString();
            $entry = array_merge(['date' => $dateString], $zero);

            foreach ($byDate->get($dateString, []) as $row) {
                $entry = $reduce($entry, $row);
            }

            $series[] = $entry;
        }

        return $series;
    }
}
