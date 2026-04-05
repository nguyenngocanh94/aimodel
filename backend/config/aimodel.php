<?php

return [
    'cache_ttl_days' => (int) env('AIMODEL_CACHE_TTL_DAYS', 7),
    'cache_max_entries' => (int) env('AIMODEL_CACHE_MAX_ENTRIES', 10000),
];
