{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('blog.index') }}</loc>
        @if($posts->isNotEmpty())<lastmod>{{ $posts->max('published_at')->toAtomString() }}</lastmod>@endif
        <changefreq>daily</changefreq>
    </url>
    @foreach($posts as $post)
        <url>
            <loc>{{ route('blog.show', $post) }}</loc>
            <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
