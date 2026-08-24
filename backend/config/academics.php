<?php

// PRD Section 3 — default confirmed with product owner 2026-08-24: lock grade
// entry during the gap between a period closing and the next one opening.
// Flip to 'auto_open' if that default is ever overridden; consumed by the
// Phase 3 grade-entry endpoint (POST /results).
return [
    'period_gap_behavior' => 'lock',
];
