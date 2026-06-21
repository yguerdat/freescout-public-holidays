<?php

namespace Modules\PublicHolidays\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PublicHolidays\Entities\PublicHoliday;
use Modules\PublicHolidays\Services\HolidayService;

class ApiController extends Controller
{
    /**
     * CORS preflight.
     */
    public function options()
    {
        return response('', 204);
    }

    /**
     * GET /api/publicholidays/status[?date=YYYY-MM-DD&locale=fr]
     *
     * Tells consuming apps whether the office is open and, if not, why and
     * when it reopens. This is what the eSéances platform calls to decide
     * whether to show a "we are closed today" message to the customer.
     */
    public function status(Request $request)
    {
        $service = new HolidayService();
        $date = $request->input('date');

        if ($date && !$this->isValidDate($date)) {
            return response()->json(['error' => 'Invalid date, expected YYYY-MM-DD.'], 422);
        }

        if ($locale = $request->input('locale')) {
            app()->setLocale($locale);
        }

        return response()->json($service->status($date));
    }

    /**
     * GET /api/publicholidays[?year=YYYY&canton=CH-JU&locale=fr]
     */
    public function index(Request $request)
    {
        $service = new HolidayService();
        $year = (int) $request->input('year', date('Y'));
        $canton = $request->input('canton');

        if ($locale = $request->input('locale')) {
            app()->setLocale($locale);
        }

        $cantons = $canton ? [$canton] : $service->getCantons();
        $holidays = PublicHoliday::forYear($year, $cantons);

        return response()->json([
            'year'     => $year,
            'cantons'  => $cantons,
            'count'    => $holidays->count(),
            'holidays' => $holidays->map(function ($h) {
                return $h->toApiArray();
            })->values(),
        ]);
    }

    /**
     * GET /api/publicholidays/upcoming[?limit=10&locale=fr]
     */
    public function upcoming(Request $request)
    {
        $service = new HolidayService();
        $limit = min(100, max(1, (int) $request->input('limit', 10)));

        if ($locale = $request->input('locale')) {
            app()->setLocale($locale);
        }

        $holidays = PublicHoliday::upcoming($service->getCantons(), $limit);

        return response()->json([
            'count'    => $holidays->count(),
            'holidays' => $holidays->map(function ($h) {
                return $h->toApiArray();
            })->values(),
        ]);
    }

    private function isValidDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
