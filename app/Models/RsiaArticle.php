<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaArticle extends Model
{
    use HasFactory;

    protected $table = 'rsia_artikel';

    protected $guarded = [];

    protected $primaryKey = 'id';

    public function getImageAttribute($value)
    {
        if (empty($value)) return null;
        if (str_contains(config('app.url'), 'https://') || !str_contains($value, 'localhost')) {
            return str_replace('http://', 'https://', $value);
        }
        return $value;
    }
}
