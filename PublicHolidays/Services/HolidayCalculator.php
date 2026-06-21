<?php

namespace Modules\PublicHolidays\Services;

/**
 * Computes Swiss public holidays per canton, fully offline.
 *
 * Movable feasts are derived from Easter Sunday (Meeus/Jones/Butcher algorithm),
 * so no calendar PHP extension and no network access is required.
 *
 * The canton coverage matrix follows the commonly published list of Swiss
 * public holidays. It is a best-effort starting point: the canton of Jura
 * (CH-JU), which is the module's default, is verified. Generated holidays are
 * stored in the database and can be reviewed, edited or removed by an admin,
 * so any local particularity can be corrected without touching the code.
 */
class HolidayCalculator
{
    /**
     * Supported cantons (ISO 3166-2:CH codes) with their display names.
     */
    public static function cantons()
    {
        return [
            'CH-AG' => 'Aargau',
            'CH-AI' => 'Appenzell Innerrhoden',
            'CH-AR' => 'Appenzell Ausserrhoden',
            'CH-BE' => 'Bern / Berne',
            'CH-BL' => 'Basel-Landschaft',
            'CH-BS' => 'Basel-Stadt',
            'CH-FR' => 'Fribourg / Freiburg',
            'CH-GE' => 'Genève',
            'CH-GL' => 'Glarus',
            'CH-GR' => 'Graubünden / Grigioni',
            'CH-JU' => 'Jura',
            'CH-LU' => 'Luzern',
            'CH-NE' => 'Neuchâtel',
            'CH-NW' => 'Nidwalden',
            'CH-OW' => 'Obwalden',
            'CH-SG' => 'St. Gallen',
            'CH-SH' => 'Schaffhausen',
            'CH-SO' => 'Solothurn',
            'CH-SZ' => 'Schwyz',
            'CH-TG' => 'Thurgau',
            'CH-TI' => 'Ticino',
            'CH-UR' => 'Uri',
            'CH-VD' => 'Vaud',
            'CH-VS' => 'Valais / Wallis',
            'CH-ZG' => 'Zug',
            'CH-ZH' => 'Zürich',
        ];
    }

    const ALL = ['CH-AG','CH-AI','CH-AR','CH-BE','CH-BL','CH-BS','CH-FR','CH-GE','CH-GL','CH-GR','CH-JU','CH-LU','CH-NE','CH-NW','CH-OW','CH-SG','CH-SH','CH-SO','CH-SZ','CH-TG','CH-TI','CH-UR','CH-VD','CH-VS','CH-ZG','CH-ZH'];

    /**
     * Holiday definitions.
     *
     * Each entry:
     *  - key:      stable identifier (used for translations)
     *  - name:     default French name (stored when no translation is available)
     *  - type:     national | cantonal
     *  - when:     ['fixed' => [month, day]]
     *              | ['easter' => offsetInDays]
     *              | ['special' => 'jeune_genevois' | 'jeune_federal']
     *  - cantons:  list of cantons observing it (self::ALL for national)
     */
    public static function definitions()
    {
        $all = self::ALL;

        return [
            ['key' => 'new_year',         'name' => 'Nouvel An',                    'type' => 'national', 'when' => ['fixed' => [1, 1]],   'cantons' => $all],
            ['key' => 'berchtold',        'name' => 'Saint-Berchtold',              'type' => 'cantonal', 'when' => ['fixed' => [1, 2]],   'cantons' => ['CH-AG','CH-BE','CH-FR','CH-GL','CH-JU','CH-LU','CH-NE','CH-OW','CH-SH','CH-SO','CH-TG','CH-UR','CH-VD','CH-ZG','CH-ZH']],
            ['key' => 'epiphany',         'name' => 'Épiphanie',                    'type' => 'cantonal', 'when' => ['fixed' => [1, 6]],   'cantons' => ['CH-SZ','CH-TI','CH-UR']],
            ['key' => 'republic_ne',      'name' => 'Instauration de la République','type' => 'cantonal', 'when' => ['fixed' => [3, 1]],   'cantons' => ['CH-NE']],
            ['key' => 'st_joseph',        'name' => 'Saint-Joseph',                 'type' => 'cantonal', 'when' => ['fixed' => [3, 19]],  'cantons' => ['CH-GR','CH-LU','CH-NW','CH-SZ','CH-TI','CH-UR','CH-VS']],
            ['key' => 'good_friday',      'name' => 'Vendredi saint',               'type' => 'national', 'when' => ['easter' => -2],      'cantons' => array_values(array_diff($all, ['CH-TI','CH-VS']))],
            ['key' => 'easter_monday',    'name' => 'Lundi de Pâques',              'type' => 'national', 'when' => ['easter' => 1],       'cantons' => array_values(array_diff($all, ['CH-VS']))],
            ['key' => 'labour_day',       'name' => 'Fête du travail',              'type' => 'cantonal', 'when' => ['fixed' => [5, 1]],   'cantons' => ['CH-BL','CH-BS','CH-JU','CH-NE','CH-SH','CH-TG','CH-TI','CH-ZH']],
            ['key' => 'ascension',        'name' => 'Ascension',                    'type' => 'national', 'when' => ['easter' => 39],      'cantons' => $all],
            ['key' => 'whit_monday',      'name' => 'Lundi de Pentecôte',           'type' => 'national', 'when' => ['easter' => 50],      'cantons' => array_values(array_diff($all, ['CH-VS']))],
            ['key' => 'corpus_christi',   'name' => 'Fête-Dieu',                    'type' => 'cantonal', 'when' => ['easter' => 60],      'cantons' => ['CH-AI','CH-FR','CH-GR','CH-JU','CH-LU','CH-NW','CH-OW','CH-SO','CH-SZ','CH-TI','CH-UR','CH-VS','CH-ZG']],
            ['key' => 'jura_plebiscite',  'name' => 'Commémoration du plébiscite jurassien', 'type' => 'cantonal', 'when' => ['fixed' => [6, 23]], 'cantons' => ['CH-JU']],
            ['key' => 'national_day',     'name' => 'Fête nationale suisse',        'type' => 'national', 'when' => ['fixed' => [8, 1]],   'cantons' => $all],
            ['key' => 'assumption',       'name' => 'Assomption',                   'type' => 'cantonal', 'when' => ['fixed' => [8, 15]],  'cantons' => ['CH-AI','CH-FR','CH-GR','CH-JU','CH-LU','CH-NW','CH-OW','CH-SO','CH-SZ','CH-TI','CH-UR','CH-VS','CH-ZG']],
            ['key' => 'jeune_genevois',   'name' => 'Jeûne genevois',               'type' => 'cantonal', 'when' => ['special' => 'jeune_genevois'], 'cantons' => ['CH-GE']],
            ['key' => 'jeune_federal',    'name' => 'Lundi du Jeûne fédéral',       'type' => 'cantonal', 'when' => ['special' => 'jeune_federal'],   'cantons' => ['CH-VD']],
            ['key' => 'bruder_klaus',     'name' => 'Fête de saint Nicolas de Flüe','type' => 'cantonal', 'when' => ['fixed' => [9, 25]],  'cantons' => ['CH-OW']],
            ['key' => 'all_saints',       'name' => 'Toussaint',                    'type' => 'cantonal', 'when' => ['fixed' => [11, 1]],  'cantons' => ['CH-AI','CH-FR','CH-GL','CH-GR','CH-JU','CH-LU','CH-NW','CH-OW','CH-SG','CH-SO','CH-SZ','CH-TI','CH-UR','CH-VS','CH-ZG']],
            ['key' => 'immaculate',       'name' => 'Immaculée Conception',         'type' => 'cantonal', 'when' => ['fixed' => [12, 8]],  'cantons' => ['CH-AI','CH-FR','CH-GR','CH-LU','CH-NW','CH-OW','CH-SZ','CH-TI','CH-UR','CH-VS','CH-ZG']],
            ['key' => 'christmas',        'name' => 'Noël',                         'type' => 'national', 'when' => ['fixed' => [12, 25]], 'cantons' => $all],
            ['key' => 'st_stephen',       'name' => 'Saint-Étienne',                'type' => 'cantonal', 'when' => ['fixed' => [12, 26]], 'cantons' => ['CH-AG','CH-AI','CH-AR','CH-BE','CH-BL','CH-BS','CH-FR','CH-GL','CH-GR','CH-LU','CH-NW','CH-OW','CH-SG','CH-SH','CH-SO','CH-SZ','CH-TG','CH-TI','CH-UR','CH-ZG','CH-ZH']],
            ['key' => 'restauration_ge',  'name' => 'Restauration de la République','type' => 'cantonal', 'when' => ['fixed' => [12, 31]], 'cantons' => ['CH-GE']],
        ];
    }

    /**
     * Compute all holidays for a canton and year.
     *
     * @return array list of ['date' => 'Y-m-d', 'key' => ..., 'name' => ..., 'type' => ...]
     */
    public function forCantonYear($canton, $year)
    {
        $year = (int) $year;
        $easter = self::easterDate($year);
        $result = [];

        foreach (self::definitions() as $def) {
            if (!in_array($canton, $def['cantons'], true)) {
                continue;
            }

            $date = $this->resolveDate($def['when'], $year, $easter);
            if (!$date) {
                continue;
            }

            $result[] = [
                'date' => $date->format('Y-m-d'),
                'key'  => $def['key'],
                'name' => $def['name'],
                'type' => $def['type'],
            ];
        }

        usort($result, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $result;
    }

    /**
     * Resolve a holiday definition's date for a given year.
     */
    private function resolveDate(array $when, $year, \DateTime $easter)
    {
        if (isset($when['fixed'])) {
            [$month, $day] = $when['fixed'];
            return new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
        }

        if (isset($when['easter'])) {
            $date = clone $easter;
            $offset = (int) $when['easter'];
            $date->modify(($offset >= 0 ? '+' : '-') . abs($offset) . ' days');
            return $date;
        }

        if (isset($when['special'])) {
            return $this->resolveSpecial($when['special'], $year);
        }

        return null;
    }

    /**
     * Special computed dates that are not tied to Easter.
     */
    private function resolveSpecial($name, $year)
    {
        if ($name === 'jeune_genevois') {
            // Thursday following the first Sunday of September.
            $firstSunday = $this->firstWeekdayOfMonth($year, 9, 0); // 0 = Sunday
            $thursday = clone $firstSunday;
            $thursday->modify('+4 days'); // Sunday + 4 = Thursday
            return $thursday;
        }

        if ($name === 'jeune_federal') {
            // Monday following the third Sunday of September.
            $firstSunday = $this->firstWeekdayOfMonth($year, 9, 0);
            $thirdSunday = clone $firstSunday;
            $thirdSunday->modify('+14 days');
            $monday = clone $thirdSunday;
            $monday->modify('+1 day');
            return $monday;
        }

        return null;
    }

    /**
     * First given weekday (0=Sunday..6=Saturday) of a month.
     */
    private function firstWeekdayOfMonth($year, $month, $weekday)
    {
        $date = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $current = (int) $date->format('w');
        $diff = ($weekday - $current + 7) % 7;
        if ($diff > 0) {
            $date->modify('+' . $diff . ' days');
        }
        return $date;
    }

    /**
     * Easter Sunday for a Gregorian year (Meeus/Jones/Butcher algorithm).
     */
    public static function easterDate($year)
    {
        $year = (int) $year;
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
