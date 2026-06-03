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
            $table->string('location')->nullable();
            $table->string('crew')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('public_id')->nullable();
            $table->string('category')->nullable();
            $table->string('activity')->nullable()->index();
            $table->string('status_label')->nullable();
            $table->string('date_label')->nullable();
            $table->string('full_date_label')->nullable();
            $table->string('time_label')->nullable();
            $table->string('location')->nullable();
            $table->string('region')->nullable();
            $table->string('price_label')->nullable();
            $table->string('spots_label')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('detail_href')->nullable();
            $table->string('booking_href')->nullable();
            $table->string('recap_href')->nullable();
            $table->string('elevation_label')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('venue_name')->nullable();
            $table->text('venue_description')->nullable();
            $table->json('summary')->nullable();
            $table->json('includes')->nullable();
            $table->json('schedule')->nullable();

            DB::getDriverName() === 'mongodb'
                ? $table->unique('public_id', null, null, ['partialFilterExpression' => ['public_id' => ['$type' => 'string']]])
                : $table->unique('public_id');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->string('public_id')->nullable();
            $table->string('description')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->string('capacity_label')->nullable();
            $table->unsignedInteger('max_per_user')->nullable();

            $table->unique(['event_id', 'public_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('attendee_name')->nullable();
            $table->string('attendee_email')->nullable();
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('booking_fee')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->string('public_id')->nullable();
            $table->string('title')->nullable();
            $table->string('event_label')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('year', 4)->nullable()->index();
            $table->string('image_alt')->nullable();

            DB::getDriverName() === 'mongodb'
                ? $table->unique('public_id', null, null, ['partialFilterExpression' => ['public_id' => ['$type' => 'string']]])
                : $table->unique('public_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropIndex(['category']);
            $table->dropIndex(['year']);
            $table->dropColumn(['public_id', 'title', 'event_label', 'category', 'year', 'image_alt']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'attendee_name',
                'attendee_email',
                'subtotal',
                'booking_fee',
                'currency',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'public_id']);
            $table->dropColumn(['public_id', 'description', 'currency', 'capacity_label', 'max_per_user']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropIndex(['activity']);
            $table->dropColumn([
                'public_id',
                'category',
                'activity',
                'status_label',
                'date_label',
                'full_date_label',
                'time_label',
                'location',
                'region',
                'price_label',
                'spots_label',
                'image_alt',
                'detail_href',
                'booking_href',
                'recap_href',
                'elevation_label',
                'difficulty',
                'venue_name',
                'venue_description',
                'summary',
                'includes',
                'schedule',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['location', 'crew']);
        });
    }
};
