<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') === 'pgsql') {
            $tables = DB::select("select tablename from pg_tables where schemaname = 'public'");
            foreach ($tables as $table) {
                DB::statement("ALTER TABLE \"{$table->tablename}\" ENABLE ROW LEVEL SECURITY;");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            $tables = DB::select("select tablename from pg_tables where schemaname = 'public'");
            foreach ($tables as $table) {
                DB::statement("ALTER TABLE \"{$table->tablename}\" DISABLE ROW LEVEL SECURITY;");
            }
        }
    }
};
