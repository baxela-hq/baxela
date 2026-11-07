<?php

return [
    // Platform-wide transfer policy, shared by the central Transfer
    // module and the per-module data endpoints (key: core.transfer.*).
    'row_cap' => 5000,

    // Upload size cap (kilobytes) for import files.
    'max_upload_kb' => 20480,

    // How many per-row errors a run record keeps before truncating.
    'error_cap' => 100,
];
