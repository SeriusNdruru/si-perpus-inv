<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Author extends Model
{
    protected $fillable = [
        'author_name',
        'biography',
    ];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'book_authors', 'author_id', 'item_id')
            ->withPivot(['author_role', 'author_order']);
    }
}
