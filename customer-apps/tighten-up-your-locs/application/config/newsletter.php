<?php

return [
    'enabled' => (bool) env('NEWSLETTER_ENABLED', false),
    'consent_version' => 'locs-newsletter-v1',
    'confirmation_hours' => 24,
    'resend_minutes' => 15,
    'daily_confirmation_limit' => 3,
];
