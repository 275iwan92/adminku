{{-- REMARK
 Tujuan       : Kotak isian lupa password (dipakai view forgot-password di kerangka desktop dan di kerangka HP).
 Dipakai oleh : auth/forgot-password.blade.php.
 Status       : Aktif.
--}}
<div class="ak-loginbox">
    <form class="ak-loginbox__form" method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <x-dl2::brand :name="config('devlib.brand.name')" :tagline="config('devlib.brand.tagline')" tone="auto" class="ak-loginbox__brand" />
        <h2 class="ak-loginbox__title">{{ \Devlib2\Lang::text('Forgot password') }}</h2>
        <p class="ak-loginbox__sub">{{ \Devlib2\Lang::text('Enter your username or e-mail. We will send a link to set a new password.') }}</p>
        @if (session('status'))<x-dl2::alert tone="success" :message="session('status')" />@endif
        @if ($errors->any())<x-dl2::alert tone="danger" :message="$errors->first()" />@endif
        <x-dl2::field.text name="login" label="Username or e-mail" icon="fa-user" size="lg" :value="old('login')" :case-toggle="false" autocomplete="username" />
        <x-dl2::button type="submit" size="lg" block label="Send reset link" />
        <p class="ak-loginbox__reg"><x-dl2::button variant="link" :href="route('login')" label="Back to sign in" /></p>
    </form>
</div>
