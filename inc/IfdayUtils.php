<?php
// IfdayUtils.php

if (!defined('DOKU_INC')) die();

class Ifday_Utils {

    /**
     * @return array Map of day abbreviations to full names.
     */
    public static function getDayAbbrMap(): array {
        $days = self::getDays();
        $dayAbbr = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        return array_combine($dayAbbr, $days);
    }

    /**
     * @return array Full day names.
     */
    public static function getDays(): array {
        return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    }

    /**
     * @return array Map of month names/abbreviations to numbers.
     */
    public static function getMonthMap(): array {
        return [
            'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3,
            'apr' => 4, 'april' => 4, 'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7,
            'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9, 'oct' => 10, 'october' => 10,
            'nov' => 11, 'november' => 11, 'dec' => 12, 'december' => 12
        ];
    }

    /**
     * Expands a day range (e.g., 'mon..fri') into an array of full day names.
     * @param string $start The starting day (e.g., 'mon' or 'monday').
     * @param string $end The ending day (e.g., 'fri' or 'friday').
     * @return array|null An array of full day names or null on error.
     */
    public static function expandDayRange(string $start, string $end): ?array {
        $days = self::getDays();
        $dayAbbr = self::getDayAbbrMap();
        $fullDayToIndex = array_flip($days);

        $start = strtolower($start);
        $end = strtolower($end);

        $startIndex = $fullDayToIndex[$dayAbbr[$start] ?? $start] ?? null;
        $endIndex = $fullDayToIndex[$dayAbbr[$end] ?? $end] ?? null;

        if ($startIndex === null || $endIndex === null) {
            return null;
        }

        $result = [];
        $currentIndex = $startIndex;
        while (true) {
            $result[] = $days[$currentIndex];
            if ($currentIndex === $endIndex) {
                break;
            }
            $currentIndex = ($currentIndex + 1) % 7;
        }
        return $result;
    }

    /**
     * Expands a month range (e.g., 'jan..mar') into an array of month numbers.
     * @param string $start The starting month (e.g., 'jan' or '1').
     * @param string $end The ending month (e.g., 'feb' or '2').
     * @return array|null An array of month numbers, or null on error.
     */
    public static function expandMonthRange(string $start, string $end): ?array {
        $monthMap = self::getMonthMap();
        $start = strtolower($start);
        $end = strtolower($end);

        if (ctype_digit($start)) {
            $startNum = (int)$start;
            if ($startNum < 1 || $startNum > 12) return null;
        } else {
            $startNum = $monthMap[$start] ?? null;
            if ($startNum === null) return null;
        }

        if (ctype_digit($end)) {
            $endNum = (int)$end;
            if ($endNum < 1 || $endNum > 12) return null;
        } else {
            $endNum = $monthMap[$end] ?? null;
            if ($endNum === null) return null;
        }

        $result = [];
        $current = $startNum;
        if ($startNum <= $endNum) {
            while ($current <= $endNum) {
                $result[] = $current++;
            }
        } else { // Wrap-around
            while ($current <= 12) {
                $result[] = $current++;
            }
            $current = 1;
            while ($current <= $endNum) {
                $result[] = $current++;
            }
        }
        return $result;
    }
}
