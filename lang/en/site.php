<?php

/**
 * Public-site interface strings — the wording the application itself writes, as opposed to the
 * content an admin types into the CMS (that is stored per-locale on the records themselves).
 *
 * Shared with the React front end through HandleInertiaRequests, so a key added here is available
 * to the components without a second file to keep in step.
 */
return [

    'nav' => [
        'menu' => 'Menu',
        'staff_login' => 'Staff login',
        'language' => 'Language',
    ],

    'home' => [
        'journals' => 'Journals',
        'view_all_journals' => 'View all journals',
        'no_journals' => 'No journals published on this site yet.',
    ],

    'journals' => [
        'title' => 'Journals',
        'subtitle' => 'Every journal published on :site',
        'articles' => '{0} No published articles|{1} :count published article|[2,*] :count published articles',
    ],

    'journal' => [
        'current_issue' => 'Current issue',
        'archive' => 'Archive',
        'no_issues' => 'No issues published yet.',
        'issue_empty' => 'This issue has no published articles yet.',
        'articles' => '{0} No articles|{1} :count article|[2,*] :count articles',
        'submit_prompt' => 'Have a manuscript ready? We welcome new submissions.',
        'register_to_submit' => 'Register to submit',
        'untitled' => 'Untitled article',
    ],

    'page' => [
        'home' => 'Home',
        'empty' => 'This page has no content yet.',
        'last_updated' => 'Last updated :date',
    ],

    'footer' => [
        'indexed_by' => 'Indexed by',
        'tools' => 'Tools',
        'explore' => 'Explore',
        'participate' => 'Participate',
        'register_to_submit' => 'Register to submit',
        'staff_login' => 'Staff login',
        'oai' => 'OAI-PMH endpoint',
        'powered_by' => 'Powered by',
        'strapline' => 'Open access · Peer reviewed · Persistently identified',
    ],

];
