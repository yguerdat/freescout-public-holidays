<?php

namespace Modules\PublicHolidays\Entities;

use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    const TYPE_NATIONAL = 'national';
    const TYPE_CANTONAL = 'cantonal';
    const TYPE_CUSTOM   = 'custom';

    protected $table = 'public_holidays';

    protected $fillable = [
        'date',
        'year',
        'canton',
        'holiday_key',
        'name',
        'type',
        'is_custom',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
    ];

    public $dates = ['date'];

    /**
     * Holidays observed by a set of cantons on a given date (Y-m-d string or Carbon).
     */
    public static function onDate($date, array $cantons)
    {
        $date = self::normalizeDate($date);

        return self::whereDate('date', $date)
            ->where(function ($q) use ($cantons) {
                $q->whereIn('canton', $cantons)->orWhere('canton', '');
            })
            ->orderBy('type')
            ->get();
    }

    /**
     * The first holiday observed by the given cantons on a date, or null.
     */
    public static function firstOnDate($date, array $cantons)
    {
        return self::onDate($date, $cantons)->first();
    }

    /**
     * All holidays for a year, optionally restricted to cantons.
     */
    public static function forYear($year, ?array $cantons = null)
    {
        $query = self::where('year', (int) $year);

        if ($cantons !== null) {
            $query->where(function ($q) use ($cantons) {
                $q->whereIn('canton', $cantons)->orWhere('canton', '');
            });
        }

        return $query->orderBy('date')->get();
    }

    /**
     * Upcoming holidays (today included) for the given cantons.
     */
    public static function upcoming(array $cantons, $limit = 10)
    {
        $today = \Carbon\Carbon::today()->toDateString();

        return self::whereDate('date', '>=', $today)
            ->where(function ($q) use ($cantons) {
                $q->whereIn('canton', $cantons)->orWhere('canton', '');
            })
            ->orderBy('date')
            ->limit($limit)
            ->get();
    }

    /**
     * Normalize a date input to a Y-m-d string.
     */
    public static function normalizeDate($date)
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return \Carbon\Carbon::parse($date)->toDateString();
    }

    /**
     * Localized name of the holiday, falling back to the stored name.
     */
    public function localizedName()
    {
        if ($this->holiday_key && $this->holiday_key !== 'custom') {
            $translated = __('publicholidays::holidays.' . $this->holiday_key);
            // If the translation key resolves, use it; otherwise the stored name.
            if ($translated && $translated !== 'publicholidays::holidays.' . $this->holiday_key) {
                return $translated;
            }
        }

        return $this->name;
    }

    /**
     * Array representation for the API.
     */
    public function toApiArray()
    {
        return [
            'date'        => \Carbon\Carbon::parse($this->date)->toDateString(),
            'name'        => $this->localizedName(),
            'key'         => $this->holiday_key,
            'canton'      => $this->canton,
            'type'        => $this->type,
            'is_custom'   => (bool) $this->is_custom,
        ];
    }
}
