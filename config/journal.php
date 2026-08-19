<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JournalScope console bypass
    |--------------------------------------------------------------------------
    |
    | Console processes (artisan, tinker, queue workers) skip the JournalScope
    | deny-by-default so seeders and jobs that already carry a trusted journal_id
    | aren't blocked. Set to false to exercise the real deny path in a test.
    |
    */

    'scope_console_bypass' => true,

];
