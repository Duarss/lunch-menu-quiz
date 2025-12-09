<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        Fortify::loginView(function () {
            return view('auth.login');
        });

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('username', $request->username)->first();

            if(app()->isProduction()){
              if(!$user->role){ throw ValidationException::withMessages(['username' => 'Anda tidak memiliki akses ke utility ini']); return false; }
              if($user->is_blocked){ throw ValidationException::withMessages(['username' => 'Akun anda terblokir. Harap hubungi penyedia layanan Anda']); return false; }
            }

            if($user && Hash::check($request->password, $user->password)){
              $user->update(['last_login_at' => now()]);
              return $user;
            }

            if (!$user) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'The provided credentials do not match our records.',
                ]);
            }

            if (!Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'The provided password is incorrect.',
                ]);
            }
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
