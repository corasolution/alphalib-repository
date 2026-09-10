<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')

        {{--
            Google Scholar citation tags, rendered server-side.

            CLAUDE.md §7 asks for Inertia SSR precisely so indexers can read article metadata;
            SSR is not configured, and Scholar does not execute our JavaScript — so without these
            in the initial HTML a published article is invisible to the single most important
            index for a journal. Emitting them from the root view gets the same result for this
            one page without standing up an SSR process.
        --}}
        @php($article = $page['props']['article'] ?? null)
        @if ($article)
            <meta name="citation_title" content="{{ $article['title'] }}">
            <meta name="citation_journal_title" content="{{ $page['props']['journal']['title'] ?? '' }}">
            @foreach ($article['authors'] ?? [] as $author)
                <meta name="citation_author" content="{{ $author['name'] }}">
                @if (! empty($author['affiliation']))
                    <meta name="citation_author_institution" content="{{ $author['affiliation'] }}">
                @endif
            @endforeach
            @if (! empty($article['publishedAt']))
                <meta name="citation_publication_date" content="{{ \Illuminate\Support\Carbon::parse($article['publishedAt'])->format('Y/m/d') }}">
            @endif
            @if (! empty($article['doi']))
                <meta name="citation_doi" content="{{ $article['doi'] }}">
            @endif
            @if (! empty($article['issue']['identification']))
                <meta name="citation_issue" content="{{ $article['issue']['identification'] }}">
            @endif
            @foreach ($article['galleys'] ?? [] as $galley)
                @if (strtoupper($galley['label']) === 'PDF')
                    <meta name="citation_pdf_url" content="{{ url()->current().'/galley/'.$galley['id'] }}">
                @endif
            @endforeach
            @if (! empty($article['abstract']))
                <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($article['abstract']), 300) }}">
            @endif
        @endif

        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
