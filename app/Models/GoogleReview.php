<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleReview extends Model
{
    use HasFactory;

    protected $table = 'google_reviews';

    protected $fillable = [
        'author_name',
        'author_url',
        'profile_photo_url',
        'rating',
        'relative_time_description',
        'text',
        'time'
    ];

    protected $casts = [
        'time' => 'integer',
        'rating' => 'integer'
    ];
}
