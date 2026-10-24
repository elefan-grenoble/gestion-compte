<?php

namespace App\DataFixtures;

class FixtureTools
{
    /**
     * Environment variable holding the seed of the fixtures' random draws.
     *
     * Set in .env.test, so the test suite and the Cypress run always get the
     * same dataset. Left unset elsewhere, which keeps dev fixtures random.
     */
    public const SEED_ENV_VAR = 'FIXTURES_SEED';

    /**
     * Seeds the Mersenne Twister when FIXTURES_SEED is set, so rand() and
     * mt_rand() (rand() is an alias of mt_rand() since PHP 7.1) draw the same
     * sequence on every load.
     *
     * Each fixture seeds itself with its own scope rather than relying on one
     * seed at the start of the run: a fixture then produces the same rows
     * whatever --group selection loaded the fixtures before it.
     */
    public static function seedRandomGenerator(string $scope): void
    {
        $seed = $_SERVER[self::SEED_ENV_VAR] ?? $_ENV[self::SEED_ENV_VAR] ?? getenv(self::SEED_ENV_VAR);
        if (false === $seed || '' === $seed) {
            return;
        }

        mt_srand(((int) $seed) ^ crc32($scope));
    }

    public static function biased_random($min, $max, $bias)
    {
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
