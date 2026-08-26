<?php

// Staff check-in/check-out window policy — confirmed with product owner 2026-08-26.
return [
    'staff' => [
        'days' => [1, 2, 3, 4, 5], // Mon–Fri (ISO-8601 weekday numbers)
        'check_in_earliest' => '07:00',
        'check_in_latest' => '08:30',
        'check_in_duration_minutes' => 90,
        'check_out_start' => '13:30',
        'check_out_duration_minutes' => 30,
    ],
];
