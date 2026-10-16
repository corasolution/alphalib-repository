<?php

namespace Database\Seeders;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\BoardGroup;
use Alpha\Journal\Models\BoardMember;
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
 * A second journal with every public field filled in: bilingual title, tagline and about text,
 * vision and mission, indexing list, publisher and ISSN, sections, three published issues, and
 * articles that carry abstracts, several authors with affiliations and ORCID iDs, DOIs and a
 * downloadable PDF — plus an editorial board for the site.
 *
 * Run on demand (`php artisan db:seed --class=SampleJournalSeeder`); it is deliberately not part
 * of DatabaseSeeder. Safe to run twice — it stops if the journal already exists.
 *
 * Everything here is invented. Authors, affiliations and ORCID iDs are fictional, the DOIs use
 * Crossref's test prefix 10.5555 (which registers nothing), and every PDF says on its face that
 * it is sample content.
 */
class SampleJournalSeeder extends Seeder
{
    private const PATH = 'cjle';

    private const DOI_PREFIX = '10.5555';

    public function run(): void
    {
        if (Journal::where('path', self::PATH)->exists()) {
            $this->command?->warn('Sample journal /'.self::PATH.' already exists — nothing to do.');

            return;
        }

        $site = Site::firstOrFail();

        $journal = Journal::create([
            'site_id' => $site->id,
            'path' => self::PATH,
            'title' => [
                'en' => 'Cambodian Journal of Law and Economics',
                'km' => 'ទស្សនាវដ្ដីច្បាប់ និងសេដ្ឋកិច្ចកម្ពុជា',
            ],
            'primary_locale' => 'en',
            'installed_locales' => ['en', 'km'],
            'is_enabled' => true,
        ]);

        if ($manager = User::where('email', 'manager@example.com')->first()) {
            $journal->grantRole($manager, JournalRole::JournalManager);
        }

        $this->seedSettings($journal);
        $sections = $this->seedSections($journal);
        $this->seedIssues($journal, $sections);
        $this->seedBoard($site);

        $this->command?->info('Seeded sample journal /'.self::PATH.' with 3 issues, '.Publication::count().' articles in total, and an editorial board.');
    }

    private function seedSettings(Journal $journal): void
    {
        $text = [
            'tagline' => [
                'en' => 'Where legal scholarship meets economic evidence',
                'km' => 'កន្លែងដែលការស្រាវជ្រាវច្បាប់ជួបនឹងភស្តុតាងសេដ្ឋកិច្ច',
            ],
            'about' => [
                'en' => "The Cambodian Journal of Law and Economics is a peer-reviewed, open-access journal publishing original research on law, regulation and economic policy in Cambodia and the wider Mekong region.\n\nWe welcome empirical studies, doctrinal analysis, comparative work and policy commentary that help courts, regulators, firms and communities understand how legal rules shape economic life — and how economic evidence can improve the law.",
                'km' => "ទស្សនាវដ្ដីច្បាប់ និងសេដ្ឋកិច្ចកម្ពុជា គឺជាទស្សនាវដ្ដីស្រាវជ្រាវដែលមានការត្រួតពិនិត្យដោយអ្នកជំនាញ និងបើកចំហសម្រាប់អ្នកអាន។",
            ],
            'vision' => [
                'en' => 'To be the region’s trusted home for rigorous research connecting legal reform with economic development.',
            ],
            'mission' => [
                'en' => 'To give scholars and practitioners a fast, transparent route from submission to a permanently citable, freely readable article.',
            ],
        ];

        foreach ($text as $name => $byLocale) {
            foreach ($byLocale as $locale => $value) {
                $journal->settings()->updateOrCreate(['name' => $name, 'locale' => $locale], ['value' => $value]);
            }
        }

        // Settings with no language.
        $plain = [
            'indexed_by' => ['DOAJ', 'Google Scholar', 'OpenAIRE', 'Crossref', 'SSRN'],
            'publisher' => 'Royal University of Law and Economics',
            'issn_online' => '2790-0000',
            'issn_print' => '2790-0001',
            'country' => 'Cambodia',
            'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
            'review_process' => 'Double-anonymous peer review by at least two independent reviewers, followed by an editorial decision.',
            'plagiarism_screening' => 'Every submission is screened for similarity before it enters peer review.',
            'apc_amount' => '0',
            'preservation_policy' => 'Articles are archived with the publisher and deposited for long-term preservation.',
            'doi_prefix' => self::DOI_PREFIX,
        ];

        foreach ($plain as $name => $value) {
            $journal->settings()->updateOrCreate(['name' => $name, 'locale' => ''], ['value' => $value]);
        }
    }

    /** @return array<string, Section> */
    private function seedSections(Journal $journal): array
    {
        $sections = [];

        foreach (['Original Research', 'Policy Analysis', 'Comparative Law', 'Book Reviews'] as $index => $title) {
            $sections[$title] = Section::create([
                'journal_id' => $journal->id,
                'title' => ['en' => $title],
                'abbreviation' => ['en' => strtoupper(Str::substr(Str::slug($title, ''), 0, 4))],
                'sort_order' => $index,
            ]);
        }

        return $sections;
    }

    /** @param array<string, Section> $sections */
    private function seedIssues(Journal $journal, array $sections): void
    {
        $issues = [
            ['volume' => 3, 'number' => 2, 'year' => 2026, 'title' => 'Land, Credit and Contracts', 'published' => now()->subDays(10), 'articles' => $this->currentIssueArticles()],
            ['volume' => 3, 'number' => 1, 'year' => 2026, 'title' => 'Trade and Investment Law', 'published' => now()->subMonths(5), 'articles' => $this->secondIssueArticles()],
            ['volume' => 2, 'number' => 2, 'year' => 2025, 'title' => 'Labour Markets and Regulation', 'published' => now()->subMonths(11), 'articles' => $this->thirdIssueArticles()],
        ];

        $doiCounter = 1;

        foreach ($issues as $spec) {
            $issue = Issue::create([
                'journal_id' => $journal->id,
                'volume' => $spec['volume'],
                'number' => $spec['number'],
                'year' => $spec['year'],
                'title' => ['en' => $spec['title']],
                'description' => ['en' => "Selected research on {$spec['title']}."],
                'is_published' => true,
                'publish_at' => $spec['published'],
                'doi' => sprintf('%s/cjle.%d.%d', self::DOI_PREFIX, $spec['volume'], $spec['number']),
            ]);

            foreach ($spec['articles'] as $article) {
                $this->seedArticle($journal, $issue, $sections[$article['section']], $article, $spec['published'], $doiCounter++);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $article
     */
    private function seedArticle(Journal $journal, Issue $issue, Section $section, array $article, $publishedAt, int $number): void
    {
        $submitter = User::factory()->create(['name' => $article['authors'][0][0].' '.$article['authors'][0][1]]);

        $submission = Submission::create([
            'journal_id' => $journal->id,
            'section_id' => $section->id,
            'submitted_by' => $submitter->id,
            'stage' => SubmissionStage::Published->value,
            'issue_id' => $issue->id,
            'submitted_at' => $publishedAt->copy()->subMonths(4),
        ]);

        $publication = Publication::create([
            'submission_id' => $submission->id,
            'version' => 1,
            'title' => ['en' => $article['title']],
            'abstract' => ['en' => $article['abstract']],
            'status' => 'published',
            'published_at' => $publishedAt,
            'doi' => sprintf('%s/cjle.%d.%d.%03d', self::DOI_PREFIX, $issue->volume, $issue->number, $number),
        ]);

        $submission->update(['current_publication_id' => $publication->id]);

        foreach ($article['authors'] as $sequence => [$given, $family, $affiliation, $orcid]) {
            $publication->contributors()->create([
                'given_name' => $given,
                'family_name' => $family,
                'affiliation' => $affiliation,
                'orcid' => $orcid,
                'role' => 'author',
                'sequence' => $sequence,
            ]);
        }

        PublicationGalley::storeFor(
            $publication,
            $this->samplePdf($article['title'], $article['authors'], $article['abstract'], $journal, $issue),
            'PDF',
        );
    }

    private function seedBoard(Site $site): void
    {
        if (BoardGroup::where('site_id', $site->id)->exists()) {
            return;
        }

        $groups = [
            'Editor-in-Chief' => [
                ['Prof. Dr. Sokha Vann', 'Editor-in-Chief', 'Royal University of Law and Economics'],
            ],
            'Associate Editors' => [
                ['Dr. Rathana Keo', 'Associate Editor, Commercial Law', 'Royal University of Law and Economics'],
                ['Dr. Maly Sou', 'Associate Editor, Development Economics', 'Royal University of Law and Economics'],
                ['Dr. Piseth Heng', 'Associate Editor, Public Policy', 'Cambodia Institute for Policy Studies'],
            ],
            'International Advisory Board' => [
                ['Prof. Elena Marchetti', 'Advisory Board Member', 'University of Bologna, Italy'],
                ['Prof. Kenji Okamoto', 'Advisory Board Member', 'Kyoto University, Japan'],
                ['Dr. Sarah Whitlock', 'Advisory Board Member', 'National University of Singapore'],
            ],
        ];

        $order = 0;

        foreach ($groups as $name => $members) {
            $group = BoardGroup::create(['site_id' => $site->id, 'name' => $name, 'sort_order' => $order++]);

            foreach ($members as [$memberName, $role, $affiliation]) {
                BoardMember::createFor($group, ['name' => $memberName, 'role' => $role, 'affiliation' => $affiliation]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function currentIssueArticles(): array
    {
        return [
            [
                'section' => 'Original Research',
                'title' => 'Land Titling and Smallholder Access to Credit in Cambodia',
                'abstract' => 'Formal land titles are widely expected to unlock bank credit for rural households, yet evidence from Cambodia is mixed. This sample article asks whether the 2002 systematic land registration programme changed borrowing patterns among smallholders. Using a household panel across five provinces, it compares titled and untitled parcels before and after registration and separates collateral effects from changes in perceived tenure security. The illustrative findings suggest that titles raise the likelihood of a bank loan mainly where a branch is within reach, and that microfinance lenders respond less to title status than commercial banks do. The paper closes with implications for sequencing legal reform and financial inclusion.',
                'authors' => [
                    ['Sopheap', 'Meas', 'Royal University of Law and Economics', '0000-0002-1825-0097'],
                    ['Daniel', 'Reyes', 'Asian Development Policy Institute', '0000-0001-5109-3700'],
                ],
            ],
            [
                'section' => 'Original Research',
                'title' => 'Contract Enforcement Delays and Firm Investment: Evidence from Phnom Penh Commercial Courts',
                'abstract' => 'Slow contract enforcement raises the cost of doing business, but the size of the effect on investment is rarely measured directly. This sample study links case-level court records with firm survey data to estimate how the time to judgment affects capital spending by small and medium enterprises. It reports that firms facing longer expected delays invest less in relationship-specific assets and rely more on informal arbitration. The discussion considers whether mediation and specialist commercial benches could shorten delays without weakening the right to a full hearing.',
                'authors' => [
                    ['Vichea', 'Tan', 'Royal University of Law and Economics', '0000-0003-1415-9269'],
                    ['Linda', 'Fischer', 'University of Heidelberg', null],
                    ['Chanthou', 'Seng', 'Cambodian Chamber of Commerce', null],
                ],
            ],
            [
                'section' => 'Policy Analysis',
                'title' => 'Digital Payments Regulation and Consumer Protection in Emerging Markets',
                'abstract' => 'Mobile and QR-based payments have expanded rapidly across Southeast Asia, while consumer protection rules have often followed rather than led. This sample policy analysis reviews the regulatory frameworks of four countries and identifies gaps in dispute resolution, liability for unauthorised transactions and data handling. It proposes a short set of minimum safeguards that regulators could adopt without slowing innovation, and sets out how each could be monitored using data that providers already collect.',
                'authors' => [
                    ['Rithy', 'Chhun', 'National Bank of Cambodia Research Unit', null],
                ],
            ],
            [
                'section' => 'Comparative Law',
                'title' => 'Investor–State Arbitration in ASEAN: A Comparative Reading of Recent Awards',
                'abstract' => 'ASEAN member states have signed a growing network of investment treaties, and the first generation of awards is now available to read side by side. This sample article compares how tribunals have treated indirect expropriation, fair and equitable treatment and the right to regulate. It finds growing convergence on the protection of legitimate expectations but continuing divergence on the weight given to public-interest regulation. The article suggests drafting choices that treaty negotiators could use to reduce uncertainty.',
                'authors' => [
                    ['Nary', 'Phan', 'Royal University of Law and Economics', '0000-0002-9079-593X'],
                    ['Thomas', 'Lindqvist', 'Stockholm University', '0000-0001-7855-4521'],
                ],
            ],
            [
                'section' => 'Book Reviews',
                'title' => 'Review: “Law and Development in Mainland Southeast Asia”',
                'abstract' => 'This sample review assesses a recent edited volume on how legal institutions have evolved alongside economic reform in the Mekong region. It praises the book’s attention to local courts and customary practice, questions the uneven coverage of the country chapters, and recommends it to readers who want an institutional rather than a doctrinal account of reform.',
                'authors' => [
                    ['Sreyneang', 'Ouk', 'Royal University of Law and Economics', null],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function secondIssueArticles(): array
    {
        return [
            [
                'section' => 'Original Research',
                'title' => 'Rules of Origin and Export Diversification in the Garment Sector',
                'abstract' => 'Preferential trade schemes depend on rules of origin that determine whether exporters can actually claim a tariff advantage. This sample article examines how changes in those rules affected the range of products exported by Cambodian garment producers. Drawing on customs records for the period, it finds that stricter sourcing requirements slowed diversification into higher-value items, while support for regional input suppliers partly offset the effect.',
                'authors' => [
                    ['Bopha', 'Lim', 'Royal University of Law and Economics', '0000-0002-3000-1234'],
                    ['Marcus', 'Adeyemi', 'University of Cape Town', null],
                ],
            ],
            [
                'section' => 'Policy Analysis',
                'title' => 'Special Economic Zones and Local Labour Standards: What Does Compliance Look Like?',
                'abstract' => 'Special economic zones are often promoted as engines of employment, but their effect on labour standards depends on how rules are inspected and enforced. This sample policy analysis compares inspection regimes inside and outside zones and sets out indicators that a ministry could publish each year to make compliance visible to workers and buyers alike.',
                'authors' => [
                    ['Sophea', 'Kong', 'Cambodia Institute for Policy Studies', null],
                    ['Anna', 'Kowalczyk', 'Warsaw School of Economics', '0000-0001-9988-7766'],
                ],
            ],
            [
                'section' => 'Comparative Law',
                'title' => 'Secured Transactions Reform: Lessons from Vietnam and Thailand',
                'abstract' => 'Modern secured-transactions laws let borrowers pledge movable assets such as stock and equipment, widening access to credit. This sample article compares the reforms adopted in Vietnam and Thailand, focusing on registry design and priority rules, and draws practical lessons for jurisdictions still relying on possessory pledges.',
                'authors' => [
                    ['Pheakdey', 'Yim', 'Royal University of Law and Economics', null],
                ],
            ],
            [
                'section' => 'Original Research',
                'title' => 'Does Mediation Reduce Litigation Costs? A Field Experiment with Small Businesses',
                'abstract' => 'Court-annexed mediation is promoted as a cheaper route to settlement, but evidence on its real cost savings is thin. In this sample study, small businesses with commercial disputes were offered mediation at random. Participants who accepted resolved their dispute faster and spent less on legal fees, although some reported weaker satisfaction with the outcome. The findings are illustrative and invented for demonstration.',
                'authors' => [
                    ['Kanha', 'Mom', 'Royal University of Law and Economics', '0000-0002-5555-0001'],
                    ['Oliver', 'Grant', 'University of Melbourne', '0000-0003-2222-3333'],
                    ['Davy', 'Sao', 'Cambodian Mediation Centre', null],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function thirdIssueArticles(): array
    {
        return [
            [
                'section' => 'Original Research',
                'title' => 'Minimum Wage Adjustments and Employment in Labour-Intensive Industries',
                'abstract' => 'Annual minimum-wage adjustments are central to labour policy in Cambodia, yet their employment effects are debated. This sample article uses factory-level payroll data to compare employment before and after successive adjustments. It reports small and statistically uncertain effects on headcount, larger effects on overtime hours, and heterogeneity by firm size.',
                'authors' => [
                    ['Samnang', 'Prak', 'Royal University of Law and Economics', null],
                    ['Hannah', 'Brooks', 'London School of Economics', '0000-0001-4444-8888'],
                ],
            ],
            [
                'section' => 'Policy Analysis',
                'title' => 'Social Protection for Informal Workers: Design Options for a Contributory Scheme',
                'abstract' => 'Most Cambodian workers are outside the formal social-security system. This sample policy analysis sets out three design options for a voluntary contributory scheme, comparing enrolment incentives, administrative cost and fiscal exposure, and recommends a phased approach beginning with workers who already hold a digital wallet.',
                'authors' => [
                    ['Chenda', 'Roth', 'Cambodia Institute for Policy Studies', null],
                ],
            ],
            [
                'section' => 'Book Reviews',
                'title' => 'Review: “Regulating Work in the Platform Economy”',
                'abstract' => 'This sample review discusses a book on the legal status of platform workers, welcoming its comparative breadth and its clear treatment of classification tests, while noting that it says little about countries where informal work remains the norm.',
                'authors' => [
                    ['Visal', 'Neang', 'Royal University of Law and Economics', null],
                ],
            ],
        ];
    }

    /**
     * A small, valid PDF standing in for the article itself.
     *
     * Says on its face that it is sample content: seed data that looks like real findings is worse
     * than seed data that looks like seed data.
     *
     * @param  list<array{0: string, 1: string, 2: ?string, 3: ?string}>  $authors
     */
    private function samplePdf(string $title, array $authors, string $abstract, Journal $journal, Issue $issue): UploadedFile
    {
        $names = implode(', ', array_map(fn ($a) => "{$a[0]} {$a[1]}", $authors));

        $lines = array_merge(
            $this->wrap($title, 62),
            ['', $names, '', $journal->title->get('en') ?? $journal->path, $issue->identification(), '', 'Abstract', ''],
            $this->wrap($abstract, 78),
            ['', ''],
            $this->wrap('SAMPLE CONTENT: this file stands in for the full text of an article included with the demonstration data. It reports no real research.', 78),
        );

        $text = "BT /F1 11 Tf 60 740 Td 15 TL\n";

        foreach ($lines as $line) {
            $text .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->ascii($line)).") Tj T*\n";
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

    /** The built-in Helvetica font has no glyphs beyond Latin-1, so typographic punctuation is flattened. */
    private function ascii(string $line): string
    {
        return strtr($line, ['–' => '-', '—' => '-', '’' => "'", '“' => '"', '”' => '"']);
    }

    /** @return list<string> */
    private function wrap(string $text, int $width): array
    {
        return explode("\n", wordwrap($text, $width, "\n", true));
    }
}
