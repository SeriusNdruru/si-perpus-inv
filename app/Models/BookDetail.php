<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookDetail extends Model
{
    /**
     * @var array<string, string>
     */
    public const GRADE_LEVELS = [
        'umum' => 'Umum / Semua Kelas',
        'kelas_1' => 'Kelas 1',
        'kelas_2' => 'Kelas 2',
        'kelas_3' => 'Kelas 3',
        'kelas_4' => 'Kelas 4',
        'kelas_5' => 'Kelas 5',
        'kelas_6' => 'Kelas 6',
    ];

    protected $primaryKey = 'item_id';

    public $incrementing = false;

    protected $fillable = [
        'item_id',
        'isbn_10',
        'isbn_13',
        'publisher_id',
        'publication_year',
        'grade_level',
        'edition',
        'language',
        'page_count',
        'classification_code',
        'call_number',
        'cover_path',
        'catalog_notes',
        'completion_status',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'page_count' => 'integer',
        ];
    }

    public function getGradeLevelLabelAttribute(): string
    {
        return self::GRADE_LEVELS[$this->grade_level ?? 'umum'] ?? 'Umum / Semua Kelas';
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
