<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });

        //instantiable
        Fortify::loginView( function () {
            return view('pages.auth.login');
        });

        //instantiable
        Fortify::registerView( function () {
            return view('pages.auth.register');
        });

        // ---------------------------------------------------- lupa password
        // Tanpa dua baris ini /forgot-password & /reset-password/{token}
        // error "RequestPasswordResetLinkViewResponse is not instantiable".
        Fortify::requestPasswordResetLinkView(fn () => view('pages.auth.forgot-password'));

        Fortify::resetPasswordView(fn (Request $request) => view('pages.auth.reset-password', ['request' => $request]));

        // Email atur ulang password dalam bahasa Indonesia
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);
            $app = \App\Models\Setting::first()?->store_name ?: config('app.name');

            return (new MailMessage)
                ->subject('Atur ulang password ' . $app)
                ->greeting('Halo, ' . $notifiable->name . '!')
                ->line('Kami menerima permintaan untuk mengatur ulang password akun Anda.')
                ->action('Buat password baru', $url)
                ->line("Tautan ini berlaku {$minutes} menit.")
                ->line('Bila Anda tidak meminta ini, abaikan saja email ini. Password Anda tidak berubah.')
                ->salutation('Salam, ' . $app);
        });
    }
}
