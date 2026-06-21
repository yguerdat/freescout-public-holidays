<?php

namespace Modules\PublicHolidays\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\PublicHolidays\Services\HolidayService;

define('PUBLICHOLIDAYS_MODULE', 'publicholidays');

class PublicHolidaysServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerTranslations();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
        $this->hooks();
    }

    public function register()
    {
        //
    }

    public function hooks()
    {
        // Module assets.
        \Eventy::addFilter('stylesheets', function ($styles) {
            $styles[] = \Module::getPublicPath(PUBLICHOLIDAYS_MODULE) . '/css/module.css';
            return $styles;
        });

        \Eventy::addFilter('javascripts', function ($javascripts) {
            $javascripts[] = \Module::getPublicPath(PUBLICHOLIDAYS_MODULE) . '/js/module.js';
            return $javascripts;
        });

        // Settings link in the global "Manage" menu (admins only).
        \Eventy::addAction('menu.manage.append', function () {
            if (auth()->user() && auth()->user()->isAdmin()) {
                echo '<li class="' . (\App\Misc\Helper::isMenuSelected('publicholidays') ? 'active' : '')
                    . '"><a href="' . route('publicholidays.settings') . '">'
                    . '<i class="glyphicon glyphicon-calendar"></i> ' . __('Public Holidays') . '</a></li>';
            }
        });

        // Inject holiday template variables into all mail templates, including
        // the customer auto-reply. On a working day every variable is empty, so
        // {%holiday.notice%} simply disappears.
        \Eventy::addFilter('mail_vars.replace', function ($vars, $data) {
            try {
                $service = new HolidayService();

                // Base the check on the conversation's creation date when known.
                $date = null;
                if (!empty($data['conversation']) && !empty($data['conversation']->created_at)) {
                    $date = $data['conversation']->created_at;
                }

                $holiday = $service->holidayOn($date);

                if ($holiday) {
                    $next = $service->nextWorkingDay($date);
                    $when = $date ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::today();

                    $vars['{%holiday.notice%}']           = $service->renderNotice($date);
                    $vars['{%holiday.name%}']             = $holiday->localizedName();
                    $vars['{%holiday.date%}']             = $when->format('d.m.Y');
                    $vars['{%holiday.is_holiday%}']       = '1';
                    $vars['{%holiday.next_working_day%}'] = $next->format('d.m.Y');
                } else {
                    $vars['{%holiday.notice%}']           = '';
                    $vars['{%holiday.name%}']             = '';
                    $vars['{%holiday.date%}']             = '';
                    $vars['{%holiday.is_holiday%}']       = '';
                    $vars['{%holiday.next_working_day%}'] = $service->nextWorkingDay($date)->format('d.m.Y');
                }
            } catch (\Exception $e) {
                \Log::error('PublicHolidays: mail_vars.replace failed: ' . $e->getMessage());
            }

            return $vars;
        }, 20, 2);

        // Optionally prefix the auto-reply subject on holidays.
        \Eventy::addFilter('email.auto_reply.subject', function ($subject, $conversation) {
            try {
                if (!\Option::get('publicholidays.subject_prefix_enabled', false)) {
                    return $subject;
                }

                $service = new HolidayService();
                $date = ($conversation && $conversation->created_at) ? $conversation->created_at : null;

                if ($service->isHoliday($date)) {
                    $prefix = \Option::get('publicholidays.subject_prefix', '');
                    if ($prefix) {
                        return trim($prefix) . ' ' . $subject;
                    }
                }
            } catch (\Exception $e) {
                \Log::error('PublicHolidays: subject filter failed: ' . $e->getMessage());
            }

            return $subject;
        }, 20, 2);

        // Show an informational banner to agents creating a conversation on a
        // holiday (so they know the office is officially closed today).
        \Eventy::addAction('conversation.create_form.before_subject', function () {
            try {
                $service = new HolidayService();
                $holiday = $service->holidayOn();

                if ($holiday) {
                    $next = $service->nextWorkingDay();
                    echo '<div class="alert alert-warning" style="margin-bottom:15px;">'
                        . '<i class="glyphicon glyphicon-calendar"></i> '
                        . e(__('Today is a public holiday (:name). The office reopens on :date.', [
                            'name' => $holiday->localizedName(),
                            'date' => $next->format('d.m.Y'),
                        ]))
                        . '</div>';
                }
            } catch (\Exception $e) {
                // Silent — never break the conversation form.
            }
        });
    }

    protected function registerConfig()
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('publicholidays.php'),
        ], 'config');
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'publicholidays');
    }

    public function registerViews()
    {
        $viewPath = resource_path('views/modules/publicholidays');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([$sourcePath => $viewPath], 'views');
        $this->loadViewsFrom(array_merge(
            array_map(function ($path) {
                return $path . '/modules/publicholidays';
            }, \Config::get('view.paths')),
            [$sourcePath]
        ), 'publicholidays');
    }

    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/publicholidays');
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'publicholidays');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'publicholidays');
        }
        $this->loadJsonTranslationsFrom(__DIR__ . '/../Resources/lang');
    }

    public function provides()
    {
        return [];
    }
}
