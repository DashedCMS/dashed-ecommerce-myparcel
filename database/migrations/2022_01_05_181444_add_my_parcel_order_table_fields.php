<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;

class AddMyParcelOrderTableFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__order_my_parcel', function (Blueprint $table) {
            $table->string('carrier')
                ->after('label_printed')
                ->nullable();
            $table->string('package_type')
                ->after('carrier')
                ->nullable();
            $table->string('delivery_type')
                ->after('package_type')
                ->nullable();
            $table->string('error')
                ->after('delivery_type')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dashed__order_my_parcel');
    }
}
