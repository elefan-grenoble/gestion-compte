<?php

namespace App\Tests\Support\Builder;

/**
 * Process-wide counter the builders draw unique defaults from (usernames,
 * member numbers, job names), so two builds never collide on a unique
 * column. It starts far above anything the fixtures or the CSV import mocks
 * create.
 */
final class UniqueSequence
{
    /** @var int */
    private static $last = 900000;

    public static function next(): int
    {
        return ++self::$last;
    }
}
