<?php

namespace Database\Seeders;

use Alpha\Journal\Models\Site;
use Illuminate\Database\Seeder;

/**
 * Khmer versions of the site's own text: title, tagline, about, highlight cards, footer and the
 * header menu labels. These are what a visitor sees when they switch the public site to Khmer;
 * anything left out falls back to the English text.
 *
 * Merges into Site.settings rather than replacing it, so it is safe to run on a site an admin has
 * already customised. It overwrites only the Khmer values below. A site admin edits the same
 * values in CMS → Homepage, Menu and Footer.
 *
 * The wording is a first draft and should be read by a Khmer speaker before it goes live.
 */
class SiteTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::firstOrFail();
        $settings = $site->settings ?? [];

        $settings['translations']['km'] = array_merge($settings['translations']['km'] ?? [], [
            'title' => 'ទស្សនាវដ្ដីសាកលវិទ្យាល័យភូមិន្ទនីតិសាស្ត្រ និងវិទ្យាសាស្ត្រសេដ្ឋកិច្ច',
            'tagline' => 'ជំរុញការស្រាវជ្រាវបើកចំហនៅទូទាំងអាស៊ីអាគ្នេយ៍',
            'about' => "Alpha Journal ផ្ទុកបណ្តាញទស្សនាវដ្ដីបើកចំហដែលកំពុងរីកចម្រើន ហើយត្រូវបានត្រួតពិនិត្យដោយអ្នកជំនាញ។ រាល់ការបោះពុម្ពផ្សាយទាំងអស់នៅលើវេទិកានេះអនុវត្តតាមដំណើរការវិចារណកថាប្រកបដោយតម្លាភាព ដាក់ស្នើលេខសម្គាល់អចិន្ត្រៃយ៍ និងត្រូវបានធ្វើលិបិក្រមសម្រាប់ការស្វែងរកក្នុងរយៈពេលវែង។\n\nយើងបង្កើតបណ្តាញនេះដើម្បីឱ្យការស្រាវជ្រាវដែលមានគុណភាព និងផ្អែកលើបរិបទតំបន់ ងាយស្រួលស្វែងរក យោង និងអភិវឌ្ឍបន្ត។",
            'footer_text' => "វេទិកាបោះពុម្ពផ្សាយសម្រាប់ការស្រាវជ្រាវដែលបើកចំហ និងត្រួតពិនិត្យដោយអ្នកជំនាញ។\nបង្កើតឡើងដើម្បីតម្លាភាពវិចារណកថា និងភាពសុចរិតនៃការរក្សាទុកក្នុងរយៈពេលវែង។",
            // Matched to the English cards by position.
            'highlights' => [
                [
                    'title' => 'ចូលដំណើរការដោយសេរី',
                    'body' => 'គំរូចូលដំណើរការដោយសេរីរបស់យើងផ្តល់អំណាចដល់អ្នកនិពន្ធ ដោយធានាការផ្សព្វផ្សាយស្នាដៃស្រាវជ្រាវដោយគ្មានដែនកំណត់ ដែលជួយពង្រីកការទទួលស្គាល់ និងឥទ្ធិពលសិក្សាទូទាំងពិភពលោក។',
                ],
                [
                    'title' => 'ចំណេះដឹងសកល',
                    'body' => 'យើងប្តេជ្ញាជំរុញចំណេះដឹងសកលពីប្រទេសកម្ពុជា ដោយផ្តល់វេទិកាដែលផ្តល់អំណាចដល់អ្នកប្រាជ្ញ និងជំរុញអនាគតនៃការពិភាក្សាសិក្សាតាមរយៈការចូលដំណើរការដោយសេរី។',
                ],
                [
                    'title' => 'ការត្រួតពិនិត្យដោយអ្នកជំនាញ',
                    'body' => 'ដំណើរការត្រួតពិនិត្យដោយអ្នកជំនាញដ៏ម៉ត់ចត់របស់យើង ធានាថាមានតែការស្រាវជ្រាវដែលមានគុណភាពខ្ពស់បំផុតពីប្រទេសកម្ពុជាប៉ុណ្ណោះដែលត្រូវបានបោះពុម្ពផ្សាយ ដោយរួមចំណែកដល់សហគមន៍សិក្សាសកល។',
                ],
            ],
            // Keyed by URL, so reordering the menu cannot attach a label to the wrong link.
            'menu_labels' => [
                '/journals' => 'ទស្សនាវដ្ដី',
                '/pages/editorial-board' => 'ក្រុមប្រឹក្សាវិចារណកថា',
                '/pages/submission-guidelines' => 'គោលការណ៍ណែនាំសម្រាប់ការដាក់ស្នើ',
                '/pages/ethics' => 'ក្រមសីលធម៌',
                '/pages/contact' => 'ទំនាក់ទំនង',
            ],
        ]);

        $site->update(['settings' => $settings]);

        $this->command?->info('Seeded Khmer translations for the site title, tagline, about, highlights, footer and menu.');
    }
}
