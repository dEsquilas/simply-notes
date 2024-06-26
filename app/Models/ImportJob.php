<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use HasFactory;

    public function notebook(){
        return $this->belongsTo(Notebook::class);
    }

}
