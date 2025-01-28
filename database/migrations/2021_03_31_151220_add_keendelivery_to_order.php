<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMyParcelToOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->string('keen_delivery_shipment_id')->nullable();
            $table->longText('keen_delivery_label')->nullable();
            $table->string('keen_delivery_label_url')->nullable();
            $table->boolean('keen_delivery_label_printed')->default(0);
            $table->string('keen_delivery_track_and_trace')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order', function (Blueprint $table) {
            //
        });
    }
}
