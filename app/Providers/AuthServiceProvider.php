<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Models\ChosenMenu;
use App\Models\Company;
use App\Models\LunchPickupWindow;
use App\Models\Menu;
use App\Models\Report;
use App\Models\User;
use App\Policies\ChosenMenuPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\LunchPickupWindowPolicy;
use App\Policies\MenuPolicy;
use App\Policies\ReportPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Company::class => CompanyPolicy::class,
        Menu::class => MenuPolicy::class,
        ChosenMenu::class => ChosenMenuPolicy::class,
        Report::class => ReportPolicy::class,
        LunchPickupWindow::class => LunchPickupWindowPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
