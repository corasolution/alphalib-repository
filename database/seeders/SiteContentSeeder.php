<?php

namespace Database\Seeders;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\HeroSlide;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\PublicationGalley;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Fills in the "Academic Precision" homepage CMS (logo, hero slideshow, tagline/about/footer,
 * menu, social links) and the per-journal public-page settings (tagline/about/vision/mission/
 * indexed_by, sections, a published issue) with real, generated content — GD-rendered images,
 * not stock photos, since this seeder has no network access — so a fresh install looks like a
 * finished product instead of the empty/graceful-fallback state every page degrades to.
 */
class SiteContentSeeder extends Seeder
{
    private const PRIMARY = [0, 66, 119];      // #004277

    private const TERTIARY = [0, 68, 110];     // #00446e

    private const ACCENT = [162, 201, 255];    // #a2c9ff

    public function run(): void
    {
        $site = Site::firstOrFail();
        $journal = Journal::where('path', 'demo')->firstOrFail();

        $this->seedSitePages($site);
        $this->seedSiteContent($site);
        $this->seedJournalContent($journal);
        $this->seedPublishedIssue($journal);

        $this->command?->info('Seeded site + journal CMS content, hero slideshow, and a published issue.');
    }

    /** @return void real static pages behind the header menu links — not everything pointing back at /journal/demo */
    private function seedSitePages(Site $site): void
    {
        $pages = [
            'editorial-board' => [
                'title' => 'Editorial Board',
                'body' => "Alpha Journal's editorial board is made up of section editors and reviewers drawn from partner universities across the region, each responsible for the sections within their field.\n\nEditorial board membership and section assignments are managed per journal — see each journal's own page for its current editors.",
            ],
            'submission-guidelines' => [
                'title' => 'Submission Guidelines',
                'body' => "Manuscripts are submitted directly through the platform after registering an account — see the \"Register to submit\" link on any journal's page.\n\nEach submission moves through a structured editorial workflow: initial review, peer review, revisions if requested, copyediting, and production, all visible to the author as a single timeline.\n\nAccepted manuscript formats: PDF, DOC, DOCX, and ODT.",
            ],
            'ethics' => [
                'title' => 'Ethics',
                'body' => "All journals on this platform follow standard peer-review integrity practices: reviewer identities are protected under double-anonymous review by default, editorial decisions are recorded and auditable, and no AI system is permitted to make or influence an editorial decision.\n\nAuthors are expected to disclose conflicts of interest and ensure submitted work is original.",
            ],
            'contact' => [
                'title' => 'Contact',
                'body' => "General inquiries: contact@example.com\n\nFor a specific journal, contact its editorial team through that journal's own page.",
            ],
        ];

        foreach ($pages as $slug => $content) {
            $site->pages()->updateOrCreate(['slug' => $slug], $content);
        }
    }

    private function seedSiteContent(Site $site): void
    {
        $site->update([
            'settings' => [
                'tagline' => 'Advancing open scholarship across Southeast Asia',
                // The numbered strip across the homepage. Seeded content, not template copy —
                // a site admin edits these three cards in CMS → Homepage like anything else.
                'highlights' => [
                    [
                        'title' => 'Open Access',
                        'body' => 'Our open-access model empowers authors by ensuring the unrestricted dissemination of their research, thereby maximising its global reach and potential for scholarly influence.',
                    ],
                    [
                        'title' => 'Global Knowledge',
                        'body' => 'We are dedicated to advancing global knowledge from Cambodia, providing a platform that empowers scholars and drives the future of academic discourse through open access.',
                    ],
                    [
                        'title' => 'Peer Review',
                        'body' => 'Our rigorous peer-review process ensures that only the highest quality research from Cambodia is published, thereby contributing to the global academic community.',
                    ],
                ],
                'about' => "Alpha Journal hosts a growing network of peer-reviewed, open-access journals. Every publication on this platform follows a transparent editorial workflow, deposits persistent identifiers, and is indexed for long-term discovery.\n\nWe built this network to make rigorous, regionally-grounded research easy to find, cite, and build on.",
                'footer_text' => "A publishing platform for open-access, peer-reviewed research.\nBuilt for editorial transparency and long-term archival integrity.",
                'menu_items' => [
                    ['label' => 'Journals', 'url' => '/journals'],
                    ['label' => 'Editorial Board', 'url' => '/pages/editorial-board'],
                    ['label' => 'Submission Guidelines', 'url' => '/pages/submission-guidelines'],
                    ['label' => 'Ethics', 'url' => '/pages/ethics'],
                    ['label' => 'Contact', 'url' => '/pages/contact'],
                ],
                'social_links' => [
                    'facebook' => 'https://facebook.com/alphajournal',
                    'twitter' => 'https://x.com/alphajournal',
                    'linkedin' => 'https://linkedin.com/company/alphajournal',
                ],
            ],
        ]);

        if ($site->logo_path === null) {
            $site->storeLogo($this->generateMark('AJ'));
        }

        if ($site->heroSlides()->count() === 0) {
            $slides = [
                ['caption' => 'Peer-reviewed research, published open access.', 'from' => self::PRIMARY, 'to' => self::TERTIARY],
                ['caption' => 'Persistent identifiers on every article, from day one.', 'from' => self::TERTIARY, 'to' => [0, 90, 158]],
                ['caption' => 'A transparent editorial workflow you can actually see.', 'from' => [0, 90, 158], 'to' => self::PRIMARY],
            ];

            foreach ($slides as $slide) {
                HeroSlide::storeFor($site, $this->generateHeroImage($slide['from'], $slide['to']), $slide['caption']);
            }
        }
    }

    private function seedJournalContent(Journal $journal): void
    {
        $texts = [
            'tagline' => 'Advancing agricultural research across the Mekong Delta',
            'about' => "We publish peer-reviewed, open-access research on tropical agriculture, food security, and rural development in Cambodia and the wider region.\n\nEvery submission passes through structured editorial review, and every accepted article is deposited to Crossref and indexed for long-term discovery.",
            'vision' => 'To be the leading platform for evidence-based agricultural research in Cambodia, recognized for rigor, openness, and regional relevance.',
            'mission' => 'To give Cambodian and regional researchers a fast, transparent path from submission to a permanently citable, freely accessible publication.',
        ];

        foreach ($texts as $name => $value) {
            $journal->settings()->updateOrCreate(['name' => $name, 'locale' => 'en'], ['value' => $value]);
        }

        $journal->settings()->updateOrCreate(
            ['name' => 'indexed_by', 'locale' => ''],
            ['value' => ['DOAJ', 'Google Scholar', 'OpenAIRE', 'Crossref']],
        );

        if (Section::where('journal_id', $journal->id)->count() === 0) {
            foreach (['Original Research', 'Review Articles', 'Case Studies'] as $index => $title) {
                Section::create(['journal_id' => $journal->id, 'title' => ['en' => $title], 'sort_order' => $index]);
            }
        }
    }

    private function seedPublishedIssue(Journal $journal): void
    {
        if (Issue::where('journal_id', $journal->id)->where('is_published', true)->exists()) {
            return;
        }

        $issue = Issue::create([
            'journal_id' => $journal->id,
            'volume' => 4,
            'number' => 2,
            'year' => (int) now()->format('Y'),
            'is_published' => true,
            'publish_at' => now(),
        ]);

        $articles = [
            ['title' => 'Rice Yield Response to Alternate Wetting and Drying Irrigation in the Mekong Delta', 'author' => ['Sopheak', 'Chan']],
            ['title' => 'Smallholder Adoption of Climate-Resilient Cassava Varieties in Battambang Province', 'author' => ['Sreymom', 'Pich']],
        ];

        foreach ($articles as $article) {
            $author = User::factory()->create(['name' => implode(' ', $article['author'])]);

            $submission = Submission::create([
                'journal_id' => $journal->id,
                'submitted_by' => $author->id,
                'stage' => SubmissionStage::Published->value,
                'issue_id' => $issue->id,
            ]);

            $publication = Publication::create([
                'submission_id' => $submission->id,
                'version' => 1,
                'title' => ['en' => $article['title']],
                'status' => 'published',
                'published_at' => now(),
            ]);

            $submission->update(['current_publication_id' => $publication->id]);
            $publication->contributors()->create([
                'given_name' => $article['author'][0],
                'family_name' => $article['author'][1],
                'sequence' => 0,
            ]);

            // Without this the demo shows published articles a reader cannot open — the article
            // page renders "No file has been published for this article yet", which is a broken
            // journal, not a demonstration of one. Publishing is the file; the title is the label.
            PublicationGalley::storeFor(
                $publication,
                $this->samplePdf($article['title'], implode(' ', $article['author']), $journal, $issue),
                'PDF',
            );
        }
    }

    /**
     * A small, valid PDF standing in for the article itself.
     *
     * Deliberately says on its face that it is sample content. Seed data that looks like real
     * findings is worse than seed data that looks like seed data — someone will eventually screenshot
     * it, and nobody should have to guess whether the numbers in it mean anything.
     */
    private function samplePdf(string $title, string $author, Journal $journal, Issue $issue): UploadedFile
    {
        $lines = array_merge(
            $this->wrap($title, 62),
            ['', $author, '', $journal->title->get('en') ?? $journal->path, $issue->identification(), '', ''],
            $this->wrap(
                'This is sample content included with the Alpha Journal demonstration data. '
                .'It stands in for the full text of the article and reports no real research.',
                72,
            ),
        );

        $text = "BT /F1 13 Tf 72 720 Td 18 TL\n";

        foreach ($lines as $line) {
            $text .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
        }

        $text .= 'ET';

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R "
                ."/Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
            "4 0 obj\n<< /Length ".strlen($text)." >>\nstream\n{$text}\nendstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $startxref = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n".'0000000000 65535 f '."\n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        $pdf .= 'trailer'."\n".'<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n"
            .'startxref'."\n".$startxref."\n".'%%EOF';

        $path = tempnam(sys_get_temp_dir(), 'galley').'.pdf';
        file_put_contents($path, $pdf);

        return new UploadedFile($path, Str::slug($title).'.pdf', 'application/pdf', null, true);
    }

    /**
     * Wraps to a column width. The PDF here has no line-breaking of its own — a long title would
     * otherwise run straight off the right edge of the page.
     *
     * @return list<string>
     */
    private function wrap(string $text, int $width): array
    {
        return explode("\n", wordwrap($text, $width, "\n", true));
    }

    /** Simple geometric wordmark: a solid rounded square + two-letter monogram. */
    private function generateMark(string $letters): UploadedFile
    {
        $size = 256;
        $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $primary = imagecolorallocate($image, ...self::PRIMARY);
        imagefilledrectangle($image, 0, 0, $size, $size, $primary);

        $white = imagecolorallocate($image, 255, 255, 255);
        $font = $this->findBoldFont();

        if ($font) {
            // imagettftext anchors at the text baseline, not a bounding-box corner — measure
            // first so a 2-letter monogram actually lands centered instead of clipped/offset.
            $fontSize = (int) ($size * 0.38);
            $box = imagettfbbox($fontSize, 0, $font, $letters);
            $textWidth = abs($box[4] - $box[0]);
            $textHeight = abs($box[5] - $box[1]);
            $x = (int) (($size - $textWidth) / 2);
            $y = (int) (($size + $textHeight) / 2);
            imagettftext($image, $fontSize, 0, $x, $y, $white, $font, $letters);
        } else {
            // No TTF available on this host — GD's built-in bitmap font is small, so stamp it
            // several times with a slight offset to fake boldness rather than ship thin text.
            $fontSize = 5;
            $textWidth = imagefontwidth($fontSize) * strlen($letters);
            $textHeight = imagefontheight($fontSize);
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    imagestring($image, $fontSize, (int) (($size - $textWidth) / 2) + $dx, (int) (($size - $textHeight) / 2) + $dy, $letters, $white);
                }
            }
        }

        return $this->storeGeneratedImage($image, 'logo.png');
    }

    /** @return string|null absolute path to a bold TTF, checked across common Windows/Linux install locations */
    private function findBoldFont(): ?string
    {
        foreach ([
            'C:\\Windows\\Fonts\\arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** A smooth diagonal gradient with a subtle dot-grid overlay, matching the hero's own CSS treatment. */
    private function generateHeroImage(array $from, array $to): UploadedFile
    {
        $width = 1600;
        $height = 600;
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int) ($from[0] + ($to[0] - $from[0]) * $ratio);
            $g = (int) ($from[1] + ($to[1] - $from[1]) * $ratio);
            $b = (int) ($from[2] + ($to[2] - $from[2]) * $ratio);
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }

        $dot = imagecolorallocatealpha($image, 255, 255, 255, 100);
        for ($x = 0; $x < $width; $x += 32) {
            for ($y = 0; $y < $height; $y += 32) {
                imagesetpixel($image, $x, $y, $dot);
            }
        }

        return $this->storeGeneratedImage($image, 'hero.jpg', IMAGETYPE_JPEG);
    }

    private function storeGeneratedImage($image, string $name = 'image.png', int $type = IMAGETYPE_PNG): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'seed_img_').($type === IMAGETYPE_JPEG ? '.jpg' : '.png');

        if ($type === IMAGETYPE_JPEG) {
            imagejpeg($image, $path, 90);
        } else {
            imagepng($image, $path);
        }

        imagedestroy($image);

        return new UploadedFile($path, $name, $type === IMAGETYPE_JPEG ? 'image/jpeg' : 'image/png', null, true);
    }
}
