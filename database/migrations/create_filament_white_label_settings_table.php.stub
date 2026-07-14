<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filament_white_label_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('panel_id');
            $table->string('tenant_key')->default('');
            $table->json('data');
            $table->timestamps();

            $table->unique(['panel_id', 'tenant_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filament_white_label_settings');
    }
};
