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
        Schema::table('users', function (Blueprint $table) {
            $table->string('location')->nullable()->after('avatar');
            $table->string('crew')->nullable()->after('location');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('public_id')->nullable()->unique()->after('id');
            $table->string('category')->nullable()->after('description');
            $table->string('activity')->nullable()->index()->after('category');
            $table->string('status_label')->nullable()->after('ends_at');
            $table->string('date_label')->nullable()->after('status_label');
            $table->string('full_date_label')->nullable()->after('date_label');
            $table->string('time_label')->nullable()->after('full_date_label');
            $table->string('location')->nullable()->after('time_label');
            $table->string('region')->nullable()->after('location');
            $table->string('price_label')->nullable()->after('region');
            $table->string('spots_label')->nullable()->after('price_label');
            $table->string('image_alt')->nullable()->after('cover_image');
            $table->string('detail_href')->nullable()->after('image_alt');
            $table->string('booking_href')->nullable()->after('detail_href');
            $table->string('recap_href')->nullable()->after('booking_href');
            $table->string('elevation_label')->nullable()->after('distance_label');
            $table->string('difficulty')->nullable()->after('elevation_label');
            $table->string('venue_name')->nullable()->after('difficulty');
            $table->text('venue_description')->nullable()->after('venue_name');
            $table->json('summary')->nullable()->after('venue_description');
            $table->json('includes')->nullable()->after('summary');
            $table->json('schedule')->nullable()->after('includes');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->string('public_id')->nullable()->after('id');
            $table->string('description')->nullable()->after('name');
            $table->string('currency', 3)->default('IDR')->after('price');
            $table->string('capacity_label')->nullable()->after('sold');
            $table->unsignedInteger('max_per_user')->nullable()->after('capacity_label');

            $table->unique(['event_id', 'public_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('attendee_name')->nullable()->after('status');
            $table->string('attendee_email')->nullable()->after('attendee_name');
            $table->unsignedInteger('subtotal')->default(0)->after('attendee_email');
            $table->unsignedInteger('booking_fee')->default(0)->after('subtotal');
            $table->string('currency', 3)->default('IDR')->after('total');
            $table->timestamp('cancelled_at')->nullable()->after('idempotency_key');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->string('public_id')->nullable()->unique()->after('id');
            $table->string('title')->nullable()->after('event_id');
            $table->string('event_label')->nullable()->after('title');
            $table->string('category')->nullable()->index()->after('event_label');
            $table->string('year', 4)->nullable()->index()->after('category');
            $table->string('image_alt')->nullable()->after('image_path');
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
