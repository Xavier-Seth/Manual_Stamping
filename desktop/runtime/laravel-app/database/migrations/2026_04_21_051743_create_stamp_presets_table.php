<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stamp_presets', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('description', 255)->nullable();

            $table->boolean('stamp_enabled')->default(true);
            $table->decimal('stamp_x', 8, 2)->default(0);
            $table->decimal('stamp_y', 8, 2)->default(0);
            $table->decimal('stamp_width', 8, 2)->default(34);
            $table->decimal('stamp_height', 8, 2)->default(16);
            $table->string('stamp_page_rule', 20)->default('all'); // all|first|last|specific
            $table->unsignedInteger('stamp_page_number')->nullable();

            $table->boolean('esign_enabled')->default(false);
            $table->decimal('esign_x', 8, 2)->nullable();
            $table->decimal('esign_y', 8, 2)->nullable();
            $table->decimal('esign_width', 8, 2)->nullable();
            $table->decimal('esign_height', 8, 2)->nullable();
            $table->string('esign_page_rule', 20)->nullable(); // last|first|specific
            $table->unsignedInteger('esign_page_number')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_presets');
    }
};