<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Garde: la table peut déjà exister (créée par la migration applicative
        // d'origine avant l'extraction en package).
        if (Schema::hasTable('sms_messages')) {
            return;
        }

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('provider_id', 36)->nullable()->unique();
            $table->string('to', 20);
            $table->text('message');
            $table->string('status', 20)->default('queued')->index();
            $table->string('error_code', 50)->nullable();
            $table->nullableMorphs('notable');
            $table->string('context', 50)->nullable();
            $table->string('webhook_delivery_id', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
