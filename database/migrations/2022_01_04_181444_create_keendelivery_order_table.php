<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;

class CreateMyParcelOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dashed__order_keendelivery', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('dashed__orders');
            $table->string('shipment_id');
            $table->longText('label')->nullable();
            $table->string('label_url')->nullable();
            $table->json('track_and_trace')->nullable();
            $table->boolean('label_printed')->default(0);

            $table->timestamps();
        });

        foreach (\Dashed\DashedEcommerceCore\Models\Order::whereNotNull('keen_delivery_shipment_id')->get() as $order) {
            $keendeliveryOrder = new MyParcelOrder();
            $keendeliveryOrder->order_id = $order->id;
            $keendeliveryOrder->shipment_id = $order->keen_delivery_shipment_id;
            $keendeliveryOrder->label = $order->keen_delivery_label;
            if (Storage::disk('dashed')->exists('/keendelivery/labels/label-' . $order->invoice_id . '.pdf')) {
                if (! Storage::disk('dashed')->exists('/orders/keendelivery/labels/label-' . $order->invoice_id . '.pdf')) {
                    Storage::disk('dashed')->copy('/keendelivery/labels/label-' . $order->invoice_id . '.pdf', '/orders/keendelivery/labels/label-' . $order->invoice_id . '.pdf');
                }
                $keendeliveryOrder->label_url = '/dashed/orders/keendelivery/labels/label-' . $order->invoice_id . '.pdf';
            }
            $keendeliveryOrder->label_printed = $order->keen_delivery_label_printed;
            $keendeliveryOrder->track_and_trace = json_decode($order->keen_delivery_track_and_trace, true);
            $keendeliveryOrder->save();
        }

        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropColumn('keen_delivery_shipment_id');
            $table->dropColumn('keen_delivery_label');
            $table->dropColumn('keen_delivery_label_url');
            $table->dropColumn('keen_delivery_label_printed');
            $table->dropColumn('keen_delivery_track_and_trace');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('keendelivery_order');
    }
}
