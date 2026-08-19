<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OAI-PMH page size
    |--------------------------------------------------------------------------
    |
    | Records per ListIdentifiers/ListRecords page before a resumptionToken is
    | issued. Overridden to a small value in tests to exercise pagination
    | without needing hundreds of fixture rows.
    |
    */

    'page_size' => env('OAI_PAGE_SIZE', 100),

];
