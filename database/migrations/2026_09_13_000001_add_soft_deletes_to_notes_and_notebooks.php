<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->index();
        });

        Schema::table('notebooks', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->index();
        });

        // Trashed rows get deleted_at set to "now", not their updated_at: otherwise the very
        // first daily purge would instantly delete trash that has been sitting there for years.
        $now = now();
        DB::table('notes')->where('status', 1)->update(['deleted_at' => $now]);
        DB::table('notebooks')->where('status', 1)->update(['deleted_at' => $now]);

        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('notebooks', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->integer('status')->default(0);
        });

        Schema::table('notebooks', function (Blueprint $table) {
            $table->integer('status')->default(0);
        });

        DB::table('notes')->whereNotNull('deleted_at')->update(['status' => 1]);
        DB::table('notebooks')->whereNotNull('deleted_at')->update(['status' => 1]);

        // SQLite refuses to drop a column that an index still references, unlike MariaDB
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropColumn('deleted_at');
        });

        Schema::table('notebooks', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropColumn('deleted_at');
        });
    }
};
