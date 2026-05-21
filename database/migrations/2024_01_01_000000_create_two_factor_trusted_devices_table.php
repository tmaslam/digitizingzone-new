<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('two_factor_trusted_devices')) {
            return;
        }

        Schema::create('two_factor_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->string('portal', 20);
            $table->string('site_legacy_key', 50)->nullable()->default(null);
            $table->unsignedBigInteger('user_id');
            $table->string('selector', 32);
            $table->string('token_hash', 64);
            $table->string('user_agent_hash', 64);
            $table->string('password_signature', 64)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index(['portal', 'user_id', 'selector']);
            $table->index(['portal', 'user_id']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_trusted_devices');
    }
};
