<h1>{{config('app.name')}}</h1>

<p>A sign-in to your account was <strong>reported</strong> from a device labelled:</p>

<p style="font-size: 18px; font-weight: bold;">{{ $device_name }}</p>

<p>Signed in at: {{ $signed_in_at }}</p>

<p>Devices are identified by the name a client reports for itself — this is not a verification of the physical device.</p>

<p>If this was you, you can safely ignore this email. If you don't recognize it, please change your password immediately and review your active sessions.</p>
