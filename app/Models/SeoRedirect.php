<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    protected $fillable = ['from_path', 'to_url', 'status_code'];

    /** Store paths as "/old-page": lower case, no trailing slash, no query string. */
    public static function normalizePath(string $path): string
    {
        return strtolower('/'.trim((string) parse_url($path, PHP_URL_PATH), '/'));
    }
}
