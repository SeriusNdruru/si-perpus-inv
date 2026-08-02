<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publisher extends Model
{
    protected $fillable = [
        'publisher_name',
        'city',
        'address',
        'phone',
        'email',
    ];

    public function bookDetails(): HasMany
    {
        return $this->hasMany(BookDetail::class);
    }
}
