<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $casts = [
        'title' => 'encrypted',
        'content' => 'encrypted',
    ];
    public function notebook(){
        return $this->belongsTo(Notebook::class);
    }

}
