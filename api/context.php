<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

enforce_rate_limit('context');
random_delay();

app_log('info', 'context_requested');

json_response([
    'ok' => true,
    'serverTime' => now(),
    'ipHash' => ip_hash(),
    'requestTokens' => [
        'create' => issue_api_request_token('create'),
        'discussionPost' => issue_api_request_token('discussion_post'),
    ],
]);
