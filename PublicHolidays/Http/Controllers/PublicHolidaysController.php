<?php

namespace Modules\PublicHolidays\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PublicHolidays\Entities\PublicHoliday;
use Modules\PublicHolidays\Services\HolidayCalculator;
use Modules\PublicHolidays\Services\HolidayService;

class PublicHolidaysController extends Controller
{
    /**
     * Settings page.
     */
    public function settings()
    {
        $service = new HolidayService();
        $year = (int) request('year', date('Y'));

        $holidays = PublicHoliday::forYear($year, $service->getCantons());

        return view('publicholidays::settings/section', [
            'service'      => $service,
            'allCantons'   => HolidayCalculator::cantons(),
            'cantons'      => $service->getCantons(),
            'year'         => $year,
            'holidays'     => $holidays,
            'todayStatus'  => $service->status(),
        ]);
    }

    /**
     * Save settings.
     */
    public function settingsSave(Request $request)
    {
        $cantons = (array) $request->input('cantons', []);
        $valid = array_keys(HolidayCalculator::cantons());
        $cantons = array_values(array_intersect($cantons, $valid));

        if (empty($cantons)) {
            $cantons = config('publicholidays.default_cantons', ['CH-JU']);
        }

        \Option::set('publicholidays.cantons', json_encode($cantons));
        \Option::set('publicholidays.weekends_closed', $request->has('weekends_closed'));

        foreach (['fr', 'en', 'de'] as $locale) {
            $tpl = $request->input('notice_template_' . $locale);
            if ($tpl !== null) {
                \Option::set('publicholidays.notice_template_' . $locale, $tpl);
            }
        }

        $subjectPrefix = $request->input('subject_prefix');
        if ($subjectPrefix !== null) {
            \Option::set('publicholidays.subject_prefix', $subjectPrefix);
        }

        \Option::set('publicholidays.subject_prefix_enabled', $request->has('subject_prefix_enabled'));

        $apiToken = $request->input('api_token');
        if ($apiToken !== null) {
            \Option::set('publicholidays.api_token', trim($apiToken));
        }

        \Session::flash('flash_success_floating', __('Settings saved.'));

        return redirect()->route('publicholidays.settings', ['year' => (int) $request->input('year', date('Y'))]);
    }

    /**
     * Generate holidays for a year (AJAX).
     */
    public function generate(Request $request)
    {
        $year = (int) $request->input('year', date('Y'));

        if ($year < 1970 || $year > 2100) {
            return response()->json(['success' => false, 'message' => __('Invalid year.')]);
        }

        $service = new HolidayService();
        $count = $service->generateYear($year);

        return response()->json([
            'success' => true,
            'message' => __(':count holidays generated for :year.', ['count' => $count, 'year' => $year]),
        ]);
    }

    /**
     * Add or update a custom holiday (AJAX).
     */
    public function storeHoliday(Request $request)
    {
        $data = $request->validate([
            'date'   => 'required|date',
            'name'   => 'required|string|max:191',
            'canton' => 'nullable|string|max:10',
        ]);

        $date = \Carbon\Carbon::parse($data['date']);
        $canton = $data['canton'] ?? '';

        $holiday = PublicHoliday::updateOrCreate(
            [
                'date'        => $date->toDateString(),
                'canton'      => $canton,
                'holiday_key' => 'custom',
            ],
            [
                'year'      => (int) $date->format('Y'),
                'name'      => $data['name'],
                'type'      => PublicHoliday::TYPE_CUSTOM,
                'is_custom' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => __('Holiday added.'),
            'holiday' => $holiday->toApiArray(),
        ]);
    }

    /**
     * Delete a holiday (AJAX).
     */
    public function deleteHoliday(Request $request)
    {
        $id = (int) $request->input('id');
        $holiday = PublicHoliday::find($id);

        if ($holiday) {
            $holiday->delete();
        }

        return response()->json(['success' => true, 'message' => __('Holiday removed.')]);
    }
}
