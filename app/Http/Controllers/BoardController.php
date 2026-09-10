<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Models\BoardGroup;
use Alpha\Journal\Models\BoardMember;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The editorial board manager. Split out from CmsController for the same reason
 * HeroSlideController was: these are individually-addressable resources with their own
 * create/reorder/delete verbs, not fields on one settings form.
 *
 * Every method authorizes SitePolicy::manage individually rather than relying on middleware,
 * matching the rest of the CMS — a route added later can't quietly inherit no protection.
 */
class BoardController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage', Site::class);

        $site = Site::firstOrFail();

        return Inertia::render('cms/editorial-board', [
            'groups' => BoardGroup::where('site_id', $site->id)
                ->with('members')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (BoardGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'members' => $group->members->map(fn (BoardMember $m) => $m->toEditorRow())->values(),
                ]),
        ]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        BoardGroup::createFor(Site::firstOrFail(), $data['name']);

        return back()->with('success', 'Group added.');
    }

    public function updateGroup(Request $request, BoardGroup $boardGroup): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $boardGroup->update($data);

        return back()->with('success', 'Group renamed.');
    }

    public function destroyGroup(BoardGroup $boardGroup): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        // Members go with it via the cascade on board_members.board_group_id.
        $boardGroup->delete();

        return back()->with('success', 'Group removed.');
    }

    public function moveGroup(Request $request, BoardGroup $boardGroup): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate(['direction' => ['required', 'in:up,down']]);

        $this->swapWithNeighbour(
            BoardGroup::where('site_id', $boardGroup->site_id),
            $boardGroup,
            $data['direction'],
        );

        return back();
    }

    public function storeMember(Request $request, BoardGroup $boardGroup): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $this->validateMember($request);

        $member = BoardMember::createFor($boardGroup, [
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'affiliation' => $data['affiliation'] ?? null,
        ]);

        if ($request->hasFile('photo')) {
            $member->storePhoto($request->file('photo'));
        }

        return back()->with('success', "{$member->name} added.");
    }

    public function updateMember(Request $request, BoardMember $boardMember): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $this->validateMember($request);

        $boardMember->update([
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'affiliation' => $data['affiliation'] ?? null,
        ]);

        if ($request->hasFile('photo')) {
            $boardMember->storePhoto($request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $boardMember->removePhoto();
        }

        return back()->with('success', 'Saved.');
    }

    public function destroyMember(BoardMember $boardMember): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $boardMember->delete();

        return back()->with('success', 'Removed.');
    }

    public function moveMember(Request $request, BoardMember $boardMember): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate(['direction' => ['required', 'in:up,down']]);

        $this->swapWithNeighbour(
            BoardMember::where('board_group_id', $boardMember->board_group_id),
            $boardMember,
            $data['direction'],
        );

        return back();
    }

    /** @return array<string, mixed> */
    private function validateMember(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['nullable', 'string', 'max:150'],
            'affiliation' => ['nullable', 'string', 'max:200'],
            'photo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.UploadLimits::imageKilobytes()],
        ]);
    }

    /**
     * Swaps a row's sort_order with its neighbour in the given scope.
     *
     * Swapping beats "decrement my sort_order", which drifts into ties and duplicate positions
     * after enough moves. Wrapped in a transaction because a half-applied swap would leave two
     * rows sharing one position, and the resulting order would then depend on insertion order.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $scope
     */
    private function swapWithNeighbour($scope, $model, string $direction): void
    {
        $neighbour = (clone $scope)
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('sort_order', '<', $model->sort_order)->orderByDesc('sort_order'),
                fn ($q) => $q->where('sort_order', '>', $model->sort_order)->orderBy('sort_order'),
            )
            ->first();

        // Already at the end of the list — a no-op, not an error.
        if (! $neighbour) {
            return;
        }

        DB::transaction(function () use ($model, $neighbour) {
            [$mine, $theirs] = [$model->sort_order, $neighbour->sort_order];

            $model->update(['sort_order' => $theirs]);
            $neighbour->update(['sort_order' => $mine]);
        });
    }
}
