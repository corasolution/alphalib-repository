        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inertia's own visits carry the CSRF cookie automatically, but the A1 manuscript
             analysis on the submission form is a plain fetch() and needs this token explicitly. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Admin-editable in CMS → Homepage, not APP_NAME: a customer renaming their platform
             should not need a deploy. --}}
        <title inertia>{{ $systemName }}</title>
        <meta name="app-name" content="{{ $systemName }}">

        {{-- Applies the saved theme before first paint. Without this the page renders light and
             then flips to the dark blue once JS hydrates — a visible flash on every load for
             anyone on a dark OS, since appearance defaults to 'system'. --}}
        <script>
            (function () {
                const saved = localStorage.getItem('appearance') || 'system';
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (saved === 'dark' || (saved === 'system' && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        {{--
             Active palette and type, server-rendered.

             Applying this from JavaScript would repaint after first paint — a visible colour flash
             on every load, the same problem the theme script below already solves for dark mode.
             Values are re-validated as hex in SiteTheme::cssVariables(); this sits inside a style
             block, so an unvalidated one would be a CSS injection.
        --}}
        <style>{!! app(\Alpha\Journal\Support\SiteTheme::class)->cssVariables(request()) !!}</style>
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="{{ app(\Alpha\Journal\Support\SiteTheme::class)->fontHref() }}" rel="stylesheet">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
