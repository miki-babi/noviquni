@php
    /** @var \Illuminate\Support\Collection<int, array{loc: string, lastmod?: string|null, changefreq?: string, priority?: string}> $urls */
@endphp
{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
        @isset($url['lastmod'])
            <lastmod>{{ $url['lastmod'] }}</lastmod>
        @endisset
        @isset($url['changefreq'])
            <changefreq>{{ $url['changefreq'] }}</changefreq>
        @endisset
        @isset($url['priority'])
            <priority>{{ $url['priority'] }}</priority>
        @endisset
    </url>
@endforeach
</urlset>
