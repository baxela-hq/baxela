<h1>{{ config('app.name') }}</h1>

<p>A new comment on a blog post is awaiting your approval:</p>

<blockquote>{{ $excerpt }}</blockquote>

<p>Review and approve or reject it in the admin panel under Content → Comments (post #{{ $post_id }}).</p>
