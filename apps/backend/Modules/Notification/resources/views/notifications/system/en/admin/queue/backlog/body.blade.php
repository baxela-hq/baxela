<h1>{{ config('app.name') }}</h1>

<p>The configured queue depth crossed its alert threshold:</p>

<ul>
    <li><strong>Queue:</strong> {{ $queue }} (connection: {{ $connection }})</li>
    <li><strong>Depth:</strong> {{ $size }} jobs (threshold: {{ $threshold }})</li>
    <li><strong>Detected at:</strong> {{ $detected_at }}</li>
</ul>

<p>Inspect pending work under <strong>Admin → Failed Jobs</strong>.</p>
