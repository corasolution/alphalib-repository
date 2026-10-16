<?php

/**
 * ⚠ DRAFT KHMER — NEEDS NATIVE REVIEW BEFORE LAUNCH ⚠
 *
 * These strings were drafted by Claude, not by a Khmer speaker. General navigation wording is
 * likely fine; the scholarly-publishing terms are the risk — "peer review", "issue", "archive",
 * "open access" and "persistent identifier" all have conventional Khmer renderings in academic
 * use that a non-specialist translation can plausibly get wrong.
 *
 * Getting this wrong on a university journal is a credibility problem, not a cosmetic one, so
 * please have someone at BELTEI read this file through before the Khmer switch is announced.
 * Any key left out of this file falls back to lang/en/site.php automatically, so deleting a line
 * you are unsure of is a safe way to defer it.
 */
return [

    'nav' => [
        'menu' => 'ម៉ឺនុយ',
        'staff_login' => 'ចូលសម្រាប់បុគ្គលិក',
        'language' => 'ភាសា',
    ],

    'home' => [
        'journals' => 'ទស្សនាវដ្ដី',
        'view_all_journals' => 'មើលទស្សនាវដ្ដីទាំងអស់',
        'no_journals' => 'មិនទាន់មានទស្សនាវដ្ដីត្រូវបានបោះពុម្ពផ្សាយនៅឡើយទេ។',
    ],

    'journals' => [
        'title' => 'ទស្សនាវដ្ដី',
        'subtitle' => 'ទស្សនាវដ្ដីទាំងអស់ដែលបោះពុម្ពផ្សាយនៅ :site',
        'articles' => '{0} មិនមានអត្ថបទបោះពុម្ពផ្សាយ|[1,*] អត្ថបទបោះពុម្ពផ្សាយ :count',
    ],

    'journal' => [
        'published_on' => 'ផ្សព្វផ្សាយ :date',
        'read_article' => 'អានអត្ថបទ',
        'current_issue' => 'លេខបច្ចុប្បន្ន',
        'archive' => 'បណ្ណសារ',
        'no_issues' => 'មិនទាន់មានលេខបោះពុម្ពផ្សាយនៅឡើយទេ។',
        'issue_empty' => 'លេខនេះមិនទាន់មានអត្ថបទបោះពុម្ពផ្សាយនៅឡើយទេ។',
        'articles' => '{0} មិនមានអត្ថបទ|[1,*] អត្ថបទ :count',
        'submit_prompt' => 'មានស្នាដៃរួចរាល់ហើយឬ? យើងស្វាគមន៍ការដាក់ស្នើថ្មី។',
        'register_to_submit' => 'ចុះឈ្មោះដើម្បីដាក់ស្នើ',
        'untitled' => 'អត្ថបទគ្មានចំណងជើង',
    ],

    'page' => [
        'home' => 'ទំព័រដើម',
        'empty' => 'ទំព័រនេះមិនទាន់មានមាតិកានៅឡើយទេ។',
        'last_updated' => 'ធ្វើបច្ចុប្បន្នភាពចុងក្រោយ :date',
    ],

    'search' => [
        'title' => 'ស្វែងរកអត្ថបទ',
        'placeholder' => 'ស្វែងរកតាមចំណងជើង អ្នកនិពន្ធ សេចក្ដីសង្ខេប ឬ DOI',
        'submit' => 'ស្វែងរក',
        'show_advanced' => 'ស្វែងរកកម្រិតខ្ពស់',
        'hide_advanced' => 'លាក់ការស្វែងរកកម្រិតខ្ពស់',
        'field_title' => 'ចំណងជើងមាន',
        'field_author' => 'អ្នកនិពន្ធ ស្ថាប័ន ឬ ORCID',
        'field_abstract' => 'សេចក្ដីសង្ខេបមាន',
        'field_journal' => 'ទស្សនាវដ្ដី',
        'field_section' => 'ផ្នែក',
        'year_from' => 'បោះពុម្ពពី',
        'year_to' => 'បោះពុម្ពដល់',
        'any' => 'ទាំងអស់',
        'sort' => 'តម្រៀបតាម',
        'sort_relevance' => 'ភាពពាក់ព័ន្ធ',
        'sort_newest' => 'ថ្មីបំផុតមុន',
        'sort_oldest' => 'ចាស់បំផុតមុន',
        'sort_title' => 'ចំណងជើង (A–Z)',
        'no_results' => 'រកមិនឃើញអត្ថបទទេ។ សាកល្បងប្រើពាក្យតិចជាង ឬស្វែងរកតាមអ្នកនិពន្ធ។',
        'results_count' => 'រកឃើញអត្ថបទ :count',
    ],
    'footer' => [
        'indexed_by' => 'ធ្វើលិបិក្រមដោយ',
        'tools' => 'ឧបករណ៍',
        'explore' => 'ស្វែងរក',
        'participate' => 'ចូលរួម',
        'handbook' => 'សៀវភៅណែនាំ',
        'register_to_submit' => 'ចុះឈ្មោះដើម្បីដាក់ស្នើ',
        'staff_login' => 'ចូលសម្រាប់បុគ្គលិក',
        'oai' => 'ចំណុចប្រទាក់ OAI-PMH',
        'powered_by' => 'ដំណើរការដោយ',
        'strapline' => 'ចូលដំណើរការដោយសេរី · ត្រួតពិនិត្យដោយអ្នកជំនាញ · មានលេខសម្គាល់អចិន្ត្រៃយ៍',
        'developed_by' => 'អភិវឌ្ឍដោយ៖ ក្រុមការងារ IT eLibrary',
    ],

];
