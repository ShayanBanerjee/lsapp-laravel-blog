<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ $seo['title'] ?? config('app.name', 'Inkfathom') }}</title>

        {{--
            Search and social metadata is rendered SERVER-SIDE on purpose.

            Inertia's <Head> injects tags from JavaScript after the page loads.
            Google executes JS and would eventually see them, but the social
            scrapers that matter for sharing — Facebook, LinkedIn, WhatsApp,
            Slack, Discord — do not run JS at all. Tags added client-side are
            invisible to every one of them, so a shared link would unfurl blank.
        --}}
        @isset($seo)
            <meta name="description" content="{{ $seo['description'] }}">
            <link rel="canonical" href="{{ $seo['canonical'] }}">

            <meta property="og:site_name" content="{{ config('app.name') }}">
            <meta property="og:type" content="{{ $seo['type'] }}">
            <meta property="og:title" content="{{ $seo['title'] }}">
            <meta property="og:description" content="{{ $seo['description'] }}">
            <meta property="og:url" content="{{ $seo['canonical'] }}">
            @if (! empty($seo['image']))
                <meta property="og:image" content="{{ $seo['image'] }}">
            @endif

            <meta name="twitter:card" content="{{ empty($seo['image']) ? 'summary' : 'summary_large_image' }}">
            <meta name="twitter:title" content="{{ $seo['title'] }}">
            <meta name="twitter:description" content="{{ $seo['description'] }}">
            @if (! empty($seo['image']))
                <meta name="twitter:image" content="{{ $seo['image'] }}">
            @endif

            @if (! empty($seo['publishedAt']))
                <meta property="article:published_time" content="{{ $seo['publishedAt'] }}">
            @endif
            @if (! empty($seo['author']))
                <meta property="article:author" content="{{ $seo['author'] }}">
            @endif

            @if (! empty($seo['jsonLd']))
                <script type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
            @endif
        @else
            <meta name="description" content="A writing platform with six worlds. Readers mark the exact sentence that landed, so writers finally know which line did the work.">
        @endisset

        <link rel="alternate" type="application/rss+xml" title="{{ config('app.name') }}" href="{{ url('/feed.xml') }}">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|instrument-serif:400,400i" rel="stylesheet" />
        {{-- Reader-selectable body faces. Loaded together so switching is instant and never flashes unstyled text. --}}
        <link href="https://fonts.bunny.net/css?family=literata:400,400i,600|source-serif-4:400,400i,600|newsreader:400,400i,600|lora:400,400i,600|inter:400,500,600|atkinson-hyperlegible:400,400i,700" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
