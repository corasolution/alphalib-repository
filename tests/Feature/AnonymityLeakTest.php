<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\ReviewType;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CLAUDE.md §4: "Anonymity is a security boundary... Every leak here is a reputational incident
 * for a customer." §9 mandates: "for each review type, assert reviewer identity is absent from
 * every API response an author or another reviewer can reach."
 */
class AnonymityLeakTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $author;

    private User $editor;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Journal A']]);
        $this->author = User::factory()->create();
        $this->editor = User::factory()->create();
        $this->reviewer = User::factory()->create(['name' => 'Reviewer Real Name']);
        $this->journal->grantRole($this->editor, JournalRole::Editor);
    }

    private function makeSubmission(ReviewType $reviewType): Submission
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Review->value,
            'review_type' => $reviewType->value,
        ]);

        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Review->value,
            'role' => JournalRole::Editor->value,
        ]);

        $round = ReviewRound::create(['submission_id' => $submission->id, 'round_number' => 1]);

        ReviewAssignment::create([
            'review_round_id' => $round->id,
            'reviewer_id' => $this->reviewer->id,
        ]);

        return $submission;
    }

    private function showUrl(Submission $submission): string
    {
        return route('journal.submissions.show', ['journal' => $this->journal->path, 'submission' => $submission->id]);
    }

    public static function nonOpenReviewTypes(): array
    {
        return [
            'anonymous' => [ReviewType::Anonymous],
            'double_anonymous' => [ReviewType::DoubleAnonymous],
        ];
    }

    #[DataProvider('nonOpenReviewTypes')]
    public function test_author_never_sees_reviewer_identity(ReviewType $reviewType): void
    {
        $submission = $this->makeSubmission($reviewType);

        $response = $this->actingAs($this->author)->getJson($this->showUrl($submission));

        $response->assertOk();
        $this->assertReviewerIdentityAbsent($response->json());
    }

    #[DataProvider('nonOpenReviewTypes')]
    public function test_response_body_never_contains_the_reviewers_name_as_a_raw_string(ReviewType $reviewType): void
    {
        $submission = $this->makeSubmission($reviewType);

        $response = $this->actingAs($this->author)->getJson($this->showUrl($submission));

        $response->assertOk();
        $this->assertStringNotContainsString('Reviewer Real Name', $response->getContent());
        $this->assertStringNotContainsString((string) $this->reviewer->id, json_encode($this->extractReviewerFields($response->json())));
    }

    public function test_editor_can_see_reviewer_identity_even_under_double_anonymous_review(): void
    {
        $submission = $this->makeSubmission(ReviewType::DoubleAnonymous);

        $response = $this->actingAs($this->editor)->getJson($this->showUrl($submission));

        $response->assertOk();
        $assignment = $response->json('review_rounds.0.review_assignments.0');
        $this->assertNotNull($assignment['reviewer']);
        $this->assertSame($this->reviewer->id, $assignment['reviewer']['id']);
    }

    public function test_open_review_reveals_reviewer_identity_to_the_author(): void
    {
        $submission = $this->makeSubmission(ReviewType::Open);

        $response = $this->actingAs($this->author)->getJson($this->showUrl($submission));

        $response->assertOk();
        $assignment = $response->json('review_rounds.0.review_assignments.0');
        $this->assertNotNull($assignment['reviewer']);
        $this->assertSame($this->reviewer->id, $assignment['reviewer']['id']);
    }

    public function test_a_stranger_cannot_view_the_submission_at_all(): void
    {
        $submission = $this->makeSubmission(ReviewType::Anonymous);
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->getJson($this->showUrl($submission));

        $response->assertForbidden();
    }

    private function assertReviewerIdentityAbsent(array $body): void
    {
        foreach ($body['review_rounds'] as $round) {
            foreach ($round['review_assignments'] as $assignment) {
                $this->assertNull($assignment['reviewer'], 'reviewer identity leaked to a non-editor viewer');
            }
        }
    }

    private function extractReviewerFields(array $body): array
    {
        return array_map(
            fn ($round) => array_map(fn ($a) => $a['reviewer'], $round['review_assignments']),
            $body['review_rounds']
        );
    }
}
