<?php

return [
    'recipient' => env('KINDLE_EMAIL'),
    // Leaves room for MIME/Base64 within Gmail's message limits.
    'max_attachment_bytes' => 18_000_000,
];
