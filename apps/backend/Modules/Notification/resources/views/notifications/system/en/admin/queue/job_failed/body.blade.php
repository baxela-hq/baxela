<h1>{{ config('app.name') }}</h1>

<p>A queued job failed after its final attempt:</p>

<ul>
    <li><strong>Job:</strong> {{ $job }}</li>
    <li><strong>Queue:</strong> {{ $queue }} (connection: {{ $connection }})</li>
    <li><strong>UUID:</strong> {{ $uuid }}</li>
    <li><strong>Failed at:</strong> {{ $failed_at }}</li>
</ul>

<p>Inspect it under <strong>Admin → Failed Jobs</strong>. Retrying re-queues the job for another attempt and may repeat its side effects — verify the original operation first.</p>
