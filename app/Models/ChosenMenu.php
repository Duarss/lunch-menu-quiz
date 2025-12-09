<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChosenMenu extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        "code",
        "menu_code",
        "chosen_by",
        "chosen_for_day",
        "is_locked",
        "chosen_at",
        "updated_at",
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_code', 'code');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'chosen_by', 'id');
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
