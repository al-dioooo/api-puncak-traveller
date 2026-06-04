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
        if (DB::getDriverName() !== 'mongodb') {
            return;
        }

        $collection = DB::connection('mongodb')->getCollection('users');

        try {
            $collection->dropIndex('google_id_1');
        } catch (Throwable) {
            // The index may not exist yet on fresh databases.
        }

        $collection->createIndex(
            ['google_id' => 1],
            [
                'name' => 'google_id_1',
                'unique' => true,
                'partialFilterExpression' => ['google_id' => ['$type' => 'string']],
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mongodb') {
            return;
        }

        DB::connection('mongodb')->getCollection('users')->dropIndex('google_id_1');
    }
};
