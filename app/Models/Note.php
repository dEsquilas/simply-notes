<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'title' => 'encrypted',
        'content' => 'encrypted',
    ];
    public function notebook(){
        return $this->belongsTo(Notebook::class);
    }

    public function versions(){
        return $this->hasMany(NoteVersion::class);
    }

}
