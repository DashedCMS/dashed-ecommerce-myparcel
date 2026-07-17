<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_my_parcel')) {
            return;
        }

        Schema::table('dashed__order_my_parcel', function (Blueprint $table): void {
            if (! Schema::hasColumn('dashed__order_my_parcel', 'options')) {
                $table->json('options')->nullable()->after('delivery_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_my_parcel')) {
            return;
        }

        Schema::table('dashed__order_my_parcel', function (Blueprint $table): void {
            if (Schema::hasColumn('dashed__order_my_parcel', 'options')) {
                $table->dropColumn('options');
            }
        });
    }
};
