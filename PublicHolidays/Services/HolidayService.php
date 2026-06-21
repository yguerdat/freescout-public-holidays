<?php

namespace Modules\PublicHolidays\Services;

use Carbon\Carbon;
use Modules\PublicHolidays\Entities\PublicHoliday;

class HolidayService
{
    /**
     * Cantons observed by the office (drives the "we are closed" logic).
     */
    public function getCantons()
    {
        $stored = \Option::get('publicholidays.cantons', null);

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        if (empty($stored) || !is_array($stored)) {
            return config('publicholidays.default_cantons', ['CH-JU']);
        }

        return array_values($stored);
    }

    /**
     * Whether weekends count as non-working days for "next working day".
     */
    public function weekendsClosed()
    {
        return (bool) \Option::get('publicholidays.weekends_closed', true);
    }

    /**
     * Generate (or regenerate) holidays for a year and the configured cantons.
     * Custom rows added by an admin are preserved.
     *
     * @return int number of holiday rows written
     */
    public function generateYear($year, array $cantons = null)
    {
        $year = (int) $year;
        $cantons = $cantons ?: $this->getCantons();
        $calculator = new HolidayCalculator();
        $written = 0;

        foreach ($cantons as $canton) {
            // Remove previously generated (non-custom) rows for this canton/year,
            // so de-selecting or correcting the matrix stays clean.
            PublicHoliday::where('year', $year)
                ->where('canton', $canton)
                ->where('is_custom', false)
                ->delete();

            foreach ($calculator->forCantonYear($canton, $year) as $h) {
                // Do not overwrite a custom row sitting on the same slot.
                $exists = PublicHoliday::where('date', $h['date'])
                    ->where('canton', $canton)
                    ->where('holiday_key', $h['key'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                PublicHoliday::create([
                    'date'        => $h['date'],
                    'year'        => $year,
                    'canton'      => $canton,
                    'holiday_key' => $h['key'],
                    'name'        => $h['name'],
                    'type'        => $h['type'],
                    'is_custom'   => false,
                ]);
                $written++;
            }
        }

        return $written;
    }

    /**
     * Holiday observed by the office on a given date (Carbon|string), or null.
     */
    public function holidayOn($date = null)
    {
        $date = $date ? $date : Carbon::today();

        return PublicHoliday::firstOnDate($date, $this->getCantons());
    }

    /**
     * Is the given date (default today) a holiday for the office?
     */
    public function isHoliday($date = null)
    {
        return $this->holidayOn($date) !== null;
    }

    /**
     * Is the office closed on the given date (holiday or, optionally, weekend)?
     */
    public function isClosed($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();

        if ($this->weekendsClosed() && $date->isWeekend()) {
            return true;
        }

        return $this->isHoliday($date);
    }

    /**
     * Next working day strictly after the given date (default today).
     */
    public function nextWorkingDay($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $lookahead = (int) config('publicholidays.next_working_day_lookahead', 30);

        $cursor = $date->copy()->addDay();
        for ($i = 0; $i < $lookahead; $i++) {
            if (!$this->isClosed($cursor)) {
                return $cursor->copy();
            }
            $cursor->addDay();
        }

        return $cursor->copy();
    }

    /**
     * Structured status payload (used by the API and the auto-reply).
     */
    public function status($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $holiday = $this->holidayOn($date);
        $closed = $this->isClosed($date);

        return [
            'date'             => $date->toDateString(),
            'cantons'          => $this->getCantons(),
            'office_open'      => !$closed,
            'is_holiday'       => $holiday !== null,
            'is_weekend'       => $date->isWeekend(),
            'holiday'          => $holiday ? $holiday->toApiArray() : null,
            'next_working_day' => $closed ? $this->nextWorkingDay($date)->toDateString() : $date->toDateString(),
        ];
    }

    /**
     * Render the holiday notice HTML for an auto-reply, or '' on a working day.
     * The template is configurable and supports {holiday}, {date} and
     * {next_working_day} placeholders.
     */
    public function renderNotice($date = null, $locale = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $holiday = $this->holidayOn($date);

        if (!$holiday) {
            return '';
        }

        if ($locale) {
            $prev = app()->getLocale();
            app()->setLocale($locale);
        }

        $template = $this->noticeTemplate($locale);
        $next = $this->nextWorkingDay($date);

        $html = strtr($template, [
            '{holiday}'          => e($holiday->localizedName()),
            '{date}'             => $date->format('d.m.Y'),
            '{next_working_day}' => $next->format('d.m.Y'),
        ]);

        if (isset($prev)) {
            app()->setLocale($prev);
        }

        return $html;
    }

    /**
     * The configured notice template for a locale, falling back to a default.
     */
    public function noticeTemplate($locale = null)
    {
        $locale = $locale ?: app()->getLocale();
        $stored = \Option::get('publicholidays.notice_template_' . $locale, null);

        if (is_string($stored) && trim($stored) !== '') {
            return $stored;
        }

        // Fallback to French template, then the built-in default.
        $fr = \Option::get('publicholidays.notice_template_fr', null);
        if (is_string($fr) && trim($fr) !== '') {
            return $fr;
        }

        return self::defaultNoticeTemplate($locale);
    }

    /**
     * Built-in default notice templates.
     */
    public static function defaultNoticeTemplate($locale = 'fr')
    {
        $defaults = [
            'fr' => '<div style="border-left:4px solid #d9822b;background:#fdf3e7;padding:12px 16px;margin-bottom:16px;font-family:sans-serif;">'
                  . '<strong>Nous sommes fermés aujourd\'hui</strong> ({holiday}, {date}).<br>'
                  . 'Votre demande a bien été enregistrée et sera traitée dès notre réouverture, le {next_working_day}.'
                  . '</div>',
            'en' => '<div style="border-left:4px solid #d9822b;background:#fdf3e7;padding:12px 16px;margin-bottom:16px;font-family:sans-serif;">'
                  . '<strong>We are closed today</strong> ({holiday}, {date}).<br>'
                  . 'Your request has been registered and will be handled when we reopen on {next_working_day}.'
                  . '</div>',
            'de' => '<div style="border-left:4px solid #d9822b;background:#fdf3e7;padding:12px 16px;margin-bottom:16px;font-family:sans-serif;">'
                  . '<strong>Wir sind heute geschlossen</strong> ({holiday}, {date}).<br>'
                  . 'Ihre Anfrage wurde registriert und wird nach unserer Wiedereröffnung am {next_working_day} bearbeitet.'
                  . '</div>',
        ];

        return $defaults[$locale] ?? $defaults['fr'];
    }
}
