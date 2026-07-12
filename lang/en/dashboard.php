<?php

declare(strict_types=1);

return [
    'title' => 'Backup Manager',
    'subtitle' => 'Backup source state and execution history.',
    'read_only' => 'Read only',
    'tables_missing' => 'Backup Manager tables are missing. Run the application migrations first.',
    'states' => ['dirty' => 'Backup required', 'clean' => 'Up to date'],
    'statuses' => ['running' => 'Running', 'successful' => 'Successful', 'failed' => 'Failed'],
    'pagination' => ['label' => 'Pagination', 'previous' => 'Previous page', 'next' => 'Next page'],
    'sources' => [
        'title' => 'Sources', 'source' => 'Source', 'type' => 'Type', 'state' => 'State',
        'last_change' => 'Last change', 'last_attempt' => 'Last attempt', 'last_success' => 'Last success',
        'last_error' => 'Last error', 'empty' => 'No backup source has been recorded yet.',
    ],
    'runs' => [
        'title' => 'Run history', 'started' => 'Started', 'source' => 'Source', 'status' => 'Status',
        'destination' => 'Destination', 'artifact' => 'Artifact', 'size' => 'Size', 'completed' => 'Completed',
        'error' => 'Error', 'empty' => 'No backup run has been recorded yet.',
    ],
];
