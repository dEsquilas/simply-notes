<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notebooks', function(Blueprint $table){
            $table->id();
            $table->string('name');
            $table->integer('owner');
            $table->timestamps();
        });

        Schema::create('notes', function(Blueprint $table){
            $table->id();
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->integer('notebook_id');
            $table->timestamps();
        });

        Schema::create('notes_attachments', function(Blueprint){
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->integer('note_id');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notebooks');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('notes_attachments');
    }
};
