<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('dashed__order_keendelivery', 'dashed__order_myparcel');
        Schema::rename('dashed__keendelivery_shipping_methods', 'dashed__myparcel_shipping_methods');
        Schema::rename('dashed__keendelivery_shipping_method_services', 'dashed__myparcel_shipping_method_services');
        Schema::rename('dashed__keendelivery_shipping_method_service_options', 'dashed__myparcel_shipping_method_service_options');
        Schema::table('dashed__myparcel_shipping_method_services', function(Blueprint $table){
           $table->renameColumn('keendelivery_shipping_method_id', 'myparcel_shipping_method_id');
        });
        Schema::table('dashed__myparcel_shipping_method_service_options', function(Blueprint $table){
           $table->renameColumn('keendelivery_shipping_method_service_id', 'myparcel_shipping_method_service_id');
        });

        foreach(\Dashed\DashedCore\Models\Customsetting::where('name', 'LIKE', '%keen_delivery%')->get() as $setting){
            $setting->name = str_replace('keen_delivery', 'myparcel', $setting->name);
            $setting->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
