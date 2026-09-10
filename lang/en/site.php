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

    'search' => [
        'title' => 'Search articles',
        'placeholder' => 'Search by title, author, abstract or DOI',
        'submit' => 'Search',
        'show_advanced' => 'Advanced search',
        'hide_advanced' => 'Hide advanced search',
        'field_title' => 'Title contains',
        'field_author' => 'Author, affiliation or ORCID',
        'field_abstract' => 'Abstract contains',
        'field_journal' => 'Journal',
        'field_section' => 'Section',
        'year_from' => 'Published from',
        'year_to' => 'Published to',
        'any' => 'Any',
        'sort' => 'Sort by',
        'sort_relevance' => 'Relevance',
        'sort_newest' => 'Newest first',
        'sort_oldest' => 'Oldest first',
        'sort_title' => 'Title (A–Z)',
        'no_results' => 'No articles matched. Try fewer words, or search by author.',
        'results_count' => ':count article found|:count articles found',
    ],
    'footer' => [
        'indexed_by' => 'Indexed by',
        'tools' => 'Tools',
        'explore' => 'Explore',
        'participate' => 'Participate',
        'handbook' => 'Handbook',
        'register_to_submit' => 'Register to submit',
        'staff_login' => 'Staff login',
        'oai' => 'OAI-PMH endpoint',
        'powered_by' => 'Powered by',
        'strapline' => 'Open access · Peer reviewed · Persistently identified',
    ],

];
