<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'note_id',
        'title',
        'content',
        'content_hash',
        'reason',
        'label',
        'pinned',
    ];

    protected $casts = [
        'title' => 'encrypted',
        'content' => 'encrypted',
        'pinned' => 'boolean',
    ];

    public function note()
    {
        return $this->belongsTo(Note::class);
    }
}
