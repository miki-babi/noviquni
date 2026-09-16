<title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}">
<meta name="robots" content="{{ $meta['robots'] }}">
<link rel="canonical" href="{{ $meta['canonical'] }}">

<meta property="og:title" content="{{ $meta['og_title'] }}">
<meta property="og:description" content="{{ $meta['og_description'] }}">
<meta property="og:type" content="{{ $meta['og_type'] }}">
<meta property="og:url" content="{{ $meta['canonical'] }}">
@if ($meta['og_image'])
    <meta property="og:image" content="{{ $meta['og_image'] }}">
@endif

<meta name="twitter:card" content="{{ $meta['twitter_card'] }}">
<meta name="twitter:title" content="{{ $meta['og_title'] }}">
<meta name="twitter:description" content="{{ $meta['og_description'] }}">
@if ($meta['og_image'])
    <meta name="twitter:image" content="{{ $meta['og_image'] }}">
@endif
