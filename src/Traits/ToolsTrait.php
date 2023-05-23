<?php


namespace App\Traits;

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
        if (!$extractedKey || array_key_exists($extractedKey, $result) || !$filter) {
            return $result;
        }

        $filteredResultBy = [];

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

}
