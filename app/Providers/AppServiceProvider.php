<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Policies\DarPolicy;
use Illuminate\Support\Facades\Gate;
use App\Models\Student;
use App\Models\DailyAccomplishmentReport;
use App\Models\WeeklyAccomplishmentReport;
use App\Models\MonthlyAccomplishmentReport;
use App\Policies\WarPolicy;
use App\Policies\StudentPolicy;
use App\Policies\MarPolicy;
class AppServiceProvider extends ServiceProvider
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
        //
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(DailyAccomplishmentReport::class, DarPolicy::class);
        Gate::policy(WeeklyAccomplishmentReport::class, WarPolicy::class);
        Gate::policy(MonthlyAccomplishmentReport::class, MarPolicy::class);
    }
}
