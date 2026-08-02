<?php

namespace App\Services;

class SchoolClassOptionsService
{
    /** @var list<string> */
    public const OPTIONS = [
        'Kelas 1',
        'Kelas 2',
        'Kelas 3',
        'Kelas 4',
        'Kelas 5',
        'Kelas 6',
    ];

    /** @return list<string> */
    public function options(): array
    {
        return self::OPTIONS;
    }
}
