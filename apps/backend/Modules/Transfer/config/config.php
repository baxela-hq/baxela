<?php

return [
    'name' => 'Transfer',

    // Upper bound on total rows across all sections of one transfer file —
    // imports and exports both enforce it to keep runs synchronous and
    // memory-bounded.
    'row_cap' => 5000,

    // Upload size cap (kilobytes) for import files.
    'max_upload_kb' => 20480,

    // How many per-row errors a run record keeps before truncating.
    'error_cap' => 100,
];
