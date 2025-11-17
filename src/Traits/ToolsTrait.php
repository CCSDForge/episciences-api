<?php


namespace App\Traits;
use App\AppConstants;
use LengthException;

trait ToolsTrait
{

    /**
     * @param array $result
     * @param string|null $extractedKey
     * @param string|null $filter
     * @return array
     */
    final public function applyFilterBy(array $result, string $extractedKey = null, string $filter = null): array
    {
        if (
            empty($result) |
            !$extractedKey ||
            isset($result[$extractedKey])
            || !$filter
        ) {
            return $result;
        }


        $filteredResultBy = [];
        $filteredResultBy[$filter] = [];


        foreach ($result as $value) {
            foreach ($value as $k => $v) {
                if ($k !== $extractedKey || (string)$v !== $filter) {
                    continue;
                }

                unset($value[$k]);

                $filteredResultBy[$filter][] = $value;
            }
        }

        return $filteredResultBy;

    }

    /**
     * Compare les deux tableaux + Calcule la différence entre eux
     * @param array $tab1
     * @param array $tab2
     * @return array
     */
    final public function checkArrayEquality(array $tab1, array $tab2): array
    {

        $result = ['equality' => false, 'arrayDiff' => []];
        $arrayIntersectTab1 = array_intersect($tab1, $tab2);
        $arrayIntersectTab2 = array_intersect($tab2, $tab1);

        if ($tab1 === $arrayIntersectTab1 && $tab2 === $arrayIntersectTab2) { //$tab1 === $tab2
            $result['equality'] = true;
        } else {
            $result['arrayDiff']['in'] = array_diff($tab1, $arrayIntersectTab1); // in $tab1
            $result['arrayDiff']['out'] = array_diff($tab2, $arrayIntersectTab2); // out of $tab1
        }

        return $result;
    }

    public static function isValidDate($date, $format = 'Y-m-d'): bool
    {
        $tDate = \DateTime::createFromFormat($format, $date);
        return $tDate && $tDate->format($format) === $date;
    }

    public static function convertToCamelCase(string $string, string $separator = '_', bool $capitalizeFirstCharacter = false): string
    {

        if (self::isInUppercase($string, $separator)) {
            $string = strtolower($string);
        }

        $str = str_replace($separator, '', ucwords($string, $separator));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }
    /**
     * @param $string
     * @param string $separator
     * @return bool
     */
    public static function isInUppercase($string, string $separator = '_'): bool
    {

        $latestSubString = '';
        foreach (explode($separator, $string) as $str) {

            $latestSubString = $str;

            if (ctype_lower($str)) {
                return false;
            }
        }

        return ctype_upper($latestSubString);

    }


    /** @throws LengthException */
    public function getMedian(array $array): int|float
    {
        if (!$array) {
            throw new LengthException('Cannot calculate median because Argument #1 ($array) is empty');
        }

        sort($array);
        $middleIndex = count($array) / 2;

        if (is_float($middleIndex)) {
            return $array[(int) $middleIndex];
        }
        return round(($array[$middleIndex] + $array[$middleIndex - 1]) / 2, AppConstants::DEFAULT_PRECISION, PHP_ROUND_HALF_UP);
    }


    public function getAvg(array $array = []): int|float|null  {

        $count = count($array);

        if ($count === 0) {
            return null;
        }

        return round(array_sum($array) / $count, AppConstants::DEFAULT_PRECISION, PHP_ROUND_HALF_UP);

    }

    /**
     * Supprime à la fois les valeurs nulles et les valeurs vides
     * @param array $array
     * @return array
     */

    public function arrayCleaner(array $array = []): array
    {
        return array_filter($array, static function($val) {
            return !(empty($val));
        });
    }
}
