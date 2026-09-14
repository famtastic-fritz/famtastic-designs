<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('newsletter_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('version')->default(0);
        });
        DB::table('newsletter_locks')->insert(['id' => 1, 'version' => 0]);
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 254)->unique();
            $table->string('status', 20)->index();
            $table->unsignedInteger('token_version')->default(0);
            $table->string('confirmation_token_hash', 64)->nullable();
            $table->timestamp('confirmation_expires_at')->nullable();
            $table->string('unsubscribe_token_hash', 64);
            $table->text('unsubscribe_token_encrypted');
            $table->string('consent_version', 60);
            $table->timestamp('consent_requested_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_confirmation_queued_at')->nullable();
            $table->date('confirmation_day')->nullable();
            $table->unsignedTinyInteger('confirmations_today')->default(0);
            $table->timestamps();
        });
        Schema::create('newsletter_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscriber_id')->index();
            $table->unsignedInteger('token_version');
            $table->string('template_id', 60);
            $table->unsignedTinyInteger('template_version');
            $table->longText('payload_encrypted');
            $table->string('status', 20)->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->uuid('lease_token')->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->string('last_error', 120)->nullable();
            $table->timestamps();
            $table->unique(['subscriber_id', 'token_version']);
            $table->foreign('subscriber_id')->references('id')->on('newsletter_subscribers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_outbox');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('newsletter_locks');
    }
};
