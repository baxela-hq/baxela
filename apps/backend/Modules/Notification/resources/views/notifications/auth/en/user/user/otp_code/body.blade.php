<h1>{{config('app.name')}}</h1>

@if($action === 'forgot_password')
    <p>Use the code below to reset your password.</p>
@else
    <p>Use the code below to verify your email address.</p>
@endif

<p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">{{$code}}</p>

<p>This code expires in {{$expires_in}} minutes. If you didn't request it, you can safely ignore this email.</p>
