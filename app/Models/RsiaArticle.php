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

        // If it's already a full URL (legacy data)
        if (str_starts_with($value, 'http')) {
            $imageUrl = $value;
        } else {
            // New data: relative path from storage
            $imageUrl = url('/storage/' . $value);
        }

        // IP vs Domain HTTPS logic
        $host = parse_url($imageUrl, PHP_URL_HOST);
        if (!str_contains($host, 'localhost') && !filter_var($host, FILTER_VALIDATE_IP)) {
            return str_replace('http://', 'https://', $imageUrl);
        }
        
        return $imageUrl;
    }
}
