<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGrabColumnsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('grab_delivery_id')->nullable()->after('restaurant_id');
            $table->decimal('grab_fee', 8, 2)->default(0)->after('grab_delivery_id');
            $table->string('grab_status')->nullable()->after('grab_fee');
            $table->string('grab_tracking_url')->nullable()->after('grab_status');
            $table->json('grab_raw_response')->nullable()->after('grab_tracking_url');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'grab_delivery_id',
                'grab_fee',
                'grab_status',
                'grab_tracking_url',
                'grab_raw_response'
            ]);
        });
    }
}