<?php

return [
    // Explicit opt-in for the inferred business schema on a remote test server.
    'enabled' => (bool) env('ALLOW_RECOVERED_SCHEMA', false),
];
