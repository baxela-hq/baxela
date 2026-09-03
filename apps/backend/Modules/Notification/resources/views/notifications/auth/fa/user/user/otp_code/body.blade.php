<h1>{{config('app.name')}}</h1>

@if($action === 'forgot_password')
    <p>برای بازنشانی گذرواژه خود از کد زیر استفاده کنید.</p>
@else
    <p>برای تایید نشانی ایمیل خود از کد زیر استفاده کنید.</p>
@endif

<p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">{{$code}}</p>

<p>این کد تا {{$expires_in}} دقیقه اعتبار دارد. اگر شما این درخواست را ارسال نکرده‌اید، این ایمیل را نادیده بگیرید.</p>
