<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMyParcelShippingMethodOptions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dashed__keendelivery_shipping_methods', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('value');
            $table->string('site_id');
            $table->boolean('enabled')->default(0);

            $table->timestamps();
        });

        Schema::create('dashed__keendelivery_shipping_method_services', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('keendelivery_shipping_method_id')->nullable();
            $table->foreign('keendelivery_shipping_method_id', 'keen_sh_me_service')->references('id')->on('dashed__keendelivery_shipping_methods')->cascadeOnDelete();
            $table->string('name');
            $table->string('value');
            $table->boolean('enabled')->default(0);

            $table->timestamps();
        });

        Schema::create('dashed__keendelivery_shipping_method_service_options', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('keendelivery_shipping_method_service_id')->nullable();
            $table->foreign('keendelivery_shipping_method_service_id', 'keen_sh_me_service_option')->references('id')->on('dashed__keendelivery_shipping_method_services')->cascadeOnDelete();
            $table->string('name');
            $table->string('field');
            $table->string('type');
            $table->boolean('mandatory')->default(0);
            $table->json('choices')->nullable();
            $table->string('default')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('keendelivery_shipping_method_options');
    }
}
