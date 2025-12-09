<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        "code",
        "name",
        "menu_date",
        "image",
        "catering",
        "created_at",
        "updated_at",
    ];

    protected $casts = [
        'menu_date' => 'date',
    ];

    public function chosenMenus()
    {
        return $this->hasMany(ChosenMenu::class, 'menu_code', 'code');
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
