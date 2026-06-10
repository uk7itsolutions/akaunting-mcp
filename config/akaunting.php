<?php

return [
    // Base URL of your Akaunting instance, no trailing slash.
    // Example: https://accounting.yourdomain.com
    'base_url' => rtrim(env('AKAUNTING_BASE_URL', ''), '/'),

    // Default company to operate on. Akaunting is multi-company and requires a
    // company_id on every request. MCP clients may override this per connection
    // by sending an "X-Company-ID" header.
    'company_id' => (int) env('AKAUNTING_COMPANY_ID', 1),
];
