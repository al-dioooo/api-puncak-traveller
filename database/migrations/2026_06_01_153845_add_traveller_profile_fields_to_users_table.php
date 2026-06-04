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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->index();
            $table->string('google_id')->nullable();
            $table->string('avatar')->nullable();

            if (DB::getDriverName() !== 'mongodb') {
                $table->unique('google_id');
            }
        });

        if (DB::getDriverName() === 'mongodb') {
            DB::connection('mongodb')->getCollection('users')->createIndex(
                ['google_id' => 1],
                [
                    'name' => 'google_id_1',
                    'unique' => true,
                    'partialFilterExpression' => ['google_id' => ['$type' => 'string']],
                ],
            );
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN password SET NOT NULL');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'google_id', 'avatar']);
        });
    }
};
