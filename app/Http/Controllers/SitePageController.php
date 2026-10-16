<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Models\BoardGroup;
use Alpha\Journal\Models\BoardMember;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\SitePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Static content pages (Editorial Board, Submission Guidelines, Ethics, Contact, ...) — the real
 * destinations for header menu links, editable by the site admin (CLAUDE.md §4's one global
 * role). Public show() is the whole reason this exists as pages rather than another
 * journal_settings text key: each one needs its own addressable /pages/{slug} URL.
 */
class SitePageController extends Controller
{
    public function index(): Response
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        return Inertia::render('cms/pages/index', [
            'pages' => $site->pages()->orderBy('title')->get(['id', 'slug', 'title']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage', Site::class);

        return Inertia::render('cms/pages/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:20000'],
            'layout' => ['nullable', 'in:editorial_board'],
            'slug' => [
                'nullable', 'string', 'max:150', 'alpha_dash',
                Rule::unique('site_pages', 'slug')->where('site_id', $site->id),
            ],
        ]);

        $site->pages()->create([
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'layout' => $data['layout'] ?? null,
            'slug' => filled($data['slug'] ?? null)
                ? $data['slug']
                : SitePage::uniqueSlugFor($site, $data['title']),
        ]);

        return redirect()->route('cms.pages.index')->with('success', 'Page created.');
    }

    public function edit(SitePage $page): Response
    {
        Gate::authorize('manage', Site::class);

        return Inertia::render('cms/pages/edit', [
            'page' => $page->only(['id', 'slug', 'title', 'body', 'layout']),
        ]);
    }

    public function update(Request $request, SitePage $page): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:20000'],
            'layout' => ['nullable', 'in:editorial_board'],
            'slug' => [
                'required', 'string', 'max:150', 'alpha_dash',
                Rule::unique('site_pages', 'slug')->where('site_id', $page->site_id)->ignore($page->id),
            ],
        ]);

        $page->update($data);

        return redirect()->route('cms.pages.index')->with('success', 'Page updated.');
    }

    public function destroy(SitePage $page): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $page->delete();

        return redirect()->route('cms.pages.index')->with('success', 'Page deleted.');
    }

    public function show(string $slug): Response
    {
        $site = Site::firstOrFail();
        $page = $site->pages()->where('slug', $slug)->firstOrFail();

        return Inertia::render('site/pages/show', [
            'site' => [
                'title' => $site->displayTitle(),
                'logoUrl' => $site->logoUrl(),
                'footerText' => $site->displayFooterText(),
            ],
            // The same header menu the homepage renders — a content page is a destination for
            // those links, so losing the nav on arrival would strand the visitor. The social
            // links are here for the same reason: without them this page's footer quietly
            // rendered without the icons every other public page shows.
            'menuItems' => $site->displayMenuItems(),
            'socialLinks' => $site->socialLinks(),
            'page' => [
                'title' => $page->title,
                'body' => $page->body,
                'layout' => $page->layout,
                'updatedAt' => $page->updated_at?->toIso8601String(),
            ],
            // Only loaded for the layout that renders it — a prose page must not carry (or pay
            // the queries for) a board it will never show.
            'boardGroups' => $page->layout === SitePage::LAYOUT_EDITORIAL_BOARD
                ? $this->boardGroups($site)
                : [],
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, members: array<int, array<string, mixed>>}>
     */
    private function boardGroups(Site $site): array
    {
        return BoardGroup::where('site_id', $site->id)
            ->with('members')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (BoardGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'members' => $group->members->map(fn (BoardMember $m) => $m->toCard())->values(),
            ])
            // An empty group is a heading with nothing under it — half-finished editing, not
            // content anyone wants on a public page.
            ->filter(fn (array $group) => count($group['members']) > 0)
            ->values()
            ->all();
    }
}
