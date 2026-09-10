<?php

namespace Tests\Feature;

use Alpha\Journal\Models\BoardGroup;
use Alpha\Journal\Models\BoardMember;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\SitePage;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The CMS editorial board: groups of people, and the page layout that renders them.
 *
 * Authorization is asserted route by route rather than once, because each method calls
 * Gate::authorize itself — a middleware-level test would pass even if one method forgot.
 */
class EditorialBoardTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create(['title' => 'Test Site']);
        $this->admin = User::factory()->create(['is_site_admin' => true]);
    }

    private function group(string $name = 'Editors-in-Chief'): BoardGroup
    {
        return BoardGroup::createFor($this->site, $name);
    }

    private function member(BoardGroup $group, string $name): BoardMember
    {
        return BoardMember::createFor($group, ['name' => $name, 'affiliation' => 'BELTEI International University, Cambodia']);
    }

    public function test_an_admin_can_add_a_group_and_people(): void
    {
        $this->actingAs($this->admin)
            ->post(route('cms.board.groups.store'), ['name' => 'Reviewers'])
            ->assertSessionHasNoErrors();

        $group = BoardGroup::sole();

        $this->actingAs($this->admin)
            ->post(route('cms.board.members.store', ['boardGroup' => $group->id]), [
                'name' => 'Dr. Som Mony',
                'role' => 'Professor of Economics',
                'affiliation' => 'BELTEI International University, Cambodia',
            ])
            ->assertSessionHasNoErrors();

        $member = BoardMember::sole();
        $this->assertSame('Dr. Som Mony', $member->name);
        $this->assertSame('Professor of Economics', $member->role);
        $this->assertSame($group->id, $member->board_group_id);
    }

    public function test_new_rows_append_rather_than_colliding_on_the_same_position(): void
    {
        $group = $this->group();
        $second = BoardGroup::createFor($this->site, 'Reviewers');

        $a = $this->member($group, 'First');
        $b = $this->member($group, 'Second');

        // Everything sharing sort_order 0 would make the displayed order depend on insertion id,
        // and the reorder buttons would then have nothing to swap.
        $this->assertNotSame($group->sort_order, $second->sort_order);
        $this->assertNotSame($a->sort_order, $b->sort_order);
        $this->assertLessThan($b->sort_order, $a->sort_order);
    }

    public function test_reordering_swaps_a_person_with_their_neighbour(): void
    {
        $group = $this->group();
        $first = $this->member($group, 'First');
        $second = $this->member($group, 'Second');

        $this->actingAs($this->admin)
            ->post(route('cms.board.members.move', ['boardMember' => $second->id]), ['direction' => 'up']);

        $this->assertSame(['Second', 'First'], $group->fresh()->members->pluck('name')->all());
    }

    public function test_moving_the_first_person_up_is_a_no_op_not_an_error(): void
    {
        $group = $this->group();
        $first = $this->member($group, 'First');
        $this->member($group, 'Second');

        $this->actingAs($this->admin)
            ->post(route('cms.board.members.move', ['boardMember' => $first->id]), ['direction' => 'up'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['First', 'Second'], $group->fresh()->members->pluck('name')->all());
    }

    public function test_deleting_a_group_takes_its_people_with_it(): void
    {
        $group = $this->group();
        $this->member($group, 'Dr. Loeung By');
        $this->member($group, 'Dr. Prak Polla');

        $this->actingAs($this->admin)->delete(route('cms.board.groups.destroy', ['boardGroup' => $group->id]));

        // Orphan members would be unreachable from every screen and never cleaned up.
        $this->assertSame(0, BoardGroup::count());
        $this->assertSame(0, BoardMember::count());
    }

    public function test_a_person_without_a_photo_renders_with_initials_instead(): void
    {
        $member = $this->member($this->group(), 'Dr. Reth Chanthoun');

        $this->assertNull($member->photoUrl());

        // Titles are stripped, or every doctor on the board gets an identical "DR" circle.
        $this->assertSame('RC', $member->initials());
        $this->assertSame('CP', $this->member($this->group('Other'), 'Chat Pound')->initials());
    }

    public function test_a_photo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('local');
        $member = $this->member($this->group(), 'Dr. Prak Polla');

        $this->actingAs($this->admin)
            ->post(route('cms.board.members.update', ['boardMember' => $member->id]), [
                'name' => 'Dr. Prak Polla',
                'photo' => UploadedFile::fake()->image('portrait.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($member->fresh()->path);

        $this->actingAs($this->admin)
            ->post(route('cms.board.members.update', ['boardMember' => $member->id]), [
                'name' => 'Dr. Prak Polla',
                'remove_photo' => true,
            ]);

        $this->assertNull($member->fresh()->path);
        $this->assertNull($member->fresh()->photoUrl());
    }

    public function test_a_page_set_to_the_board_layout_carries_the_groups(): void
    {
        $group = $this->group('Advisory Council');
        $this->member($group, 'Dr. Chheng Ly');

        $this->site->pages()->create([
            'slug' => 'editorial-board',
            'title' => 'Editorial Board',
            'body' => 'All members are affiliated with BELTEI International University, Cambodia.',
            'layout' => SitePage::LAYOUT_EDITORIAL_BOARD,
        ]);

        $this->get('/pages/editorial-board')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('boardGroups', 1)
            ->where('boardGroups.0.name', 'Advisory Council')
            ->has('boardGroups.0.members', 1)
            ->where('boardGroups.0.members.0.name', 'Dr. Chheng Ly')
            ->where('boardGroups.0.members.0.initials', 'CL')
            ->where('boardGroups.0.members.0.photoUrl', null)
        );
    }

    public function test_a_prose_page_never_carries_the_board(): void
    {
        $group = $this->group('Advisory Council');
        $this->member($group, 'Dr. Chheng Ly');

        $this->site->pages()->create(['slug' => 'ethics', 'title' => 'Ethics', 'body' => 'Our statement.']);

        // A prose page must not pay for, or leak, content it does not render.
        $this->get('/pages/ethics')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('boardGroups', 0)
            ->where('page.layout', null)
        );
    }

    public function test_an_empty_group_is_left_off_the_public_page(): void
    {
        $this->group('Advisory Council');

        $this->site->pages()->create([
            'slug' => 'editorial-board',
            'title' => 'Editorial Board',
            'layout' => SitePage::LAYOUT_EDITORIAL_BOARD,
        ]);

        // A heading with nobody under it is half-finished editing, not content.
        $this->get('/pages/editorial-board')->assertInertia(fn (AssertableInertia $page) => $page->has('boardGroups', 0));
    }

    public function test_an_unknown_layout_is_rejected(): void
    {
        $page = $this->site->pages()->create(['slug' => 'ethics', 'title' => 'Ethics']);

        $this->actingAs($this->admin)
            ->put(route('cms.pages.update', ['page' => $page->id]), [
                'title' => 'Ethics', 'slug' => 'ethics', 'layout' => 'something_else',
            ])
            ->assertSessionHasErrors('layout');
    }

    public function test_a_regular_user_cannot_touch_the_board(): void
    {
        $user = User::factory()->create();
        $group = $this->group();
        $member = $this->member($group, 'Dr. Loeung By');

        // Each route authorizes on its own, so each one is asserted on its own.
        $this->actingAs($user)->get(route('cms.board.index'))->assertForbidden();
        $this->actingAs($user)->post(route('cms.board.groups.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->put(route('cms.board.groups.update', ['boardGroup' => $group->id]), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete(route('cms.board.groups.destroy', ['boardGroup' => $group->id]))->assertForbidden();
        $this->actingAs($user)->post(route('cms.board.groups.move', ['boardGroup' => $group->id]), ['direction' => 'up'])->assertForbidden();
        $this->actingAs($user)->post(route('cms.board.members.store', ['boardGroup' => $group->id]), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->post(route('cms.board.members.update', ['boardMember' => $member->id]), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete(route('cms.board.members.destroy', ['boardMember' => $member->id]))->assertForbidden();
        $this->actingAs($user)->post(route('cms.board.members.move', ['boardMember' => $member->id]), ['direction' => 'up'])->assertForbidden();

        $this->assertSame(1, BoardGroup::count());
        $this->assertSame(1, BoardMember::count());
        $this->assertSame('Dr. Loeung By', $member->fresh()->name);
    }

    public function test_a_guest_cannot_reach_the_board_screen(): void
    {
        $this->get(route('cms.board.index'))->assertRedirect(route('login'));
    }
    public function test_a_freshly_built_user_denies_rather_than_erroring(): void
    {
        // Regression: Postgres does not return column defaults into the model on insert, so this
        // user's is_site_admin was null in memory. SitePolicy::manage() is typed `: bool`, so the
        // null became a TypeError and EVERY CMS route answered 500 instead of 403 — a denial that
        // looks like a crash, on the exact paths where being denied is the point.
        $fresh = User::factory()->create();

        $this->assertFalse($fresh->is_site_admin);
        $this->assertFalse(app(\Alpha\Journal\Policies\SitePolicy::class)->manage($fresh));

        $this->actingAs($fresh)->get(route('cms.board.index'))->assertForbidden();
        $this->actingAs($fresh)->get(route('cms.homepage.edit'))->assertForbidden();
        $this->actingAs($fresh)->get(route('cms.pages.index'))->assertForbidden();
    }
}