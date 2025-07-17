<?php


namespace AppBundle\DataFixtures;



class FixtureTools
{

    public static function biased_random($min, $max, $bias) {
        // Calculate the probability for non-maximum values
        $prob_range = (1 - $bias) / ($max - $min);

        // Generate a random float between 0 and 1
        $rand = mt_rand() / mt_getrandmax();

        // Calculate thresholds for each number
        $thresholds = [];
        for ($i = $min; $i < $max; ++$i) {
            $thresholds[$i] = $prob_range * ($i - $min + 1);
        }
        $thresholds[$max] = 1.0;  // The last threshold (for max) is always 1

        // Determine the random value based on thresholds
        foreach ($thresholds as $value => $threshold) {
            if ($rand < $threshold) {
                return $value;
            }
        }

        // Fallback (this shouldn't happen)
        return $max;
    }

}