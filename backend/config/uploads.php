<?php

// PRD Section 8, item 4 — confirmed with product owner 2026-08-24.
return [
    'photo' => [
        'mimes' => ['jpg', 'jpeg', 'png'],
        'max_kb' => 2048,
    ],
    'document' => [
        'mimes' => ['pdf'],
        'max_kb' => 5120,
    ],
];
