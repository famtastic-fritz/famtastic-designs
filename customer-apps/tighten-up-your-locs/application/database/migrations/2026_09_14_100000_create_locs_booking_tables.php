<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_resource_locks', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('lock_version')->default(0);
        });
        DB::table('booking_resource_locks')->insert(['id' => 1, 'lock_version' => 0]);
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 128)->unique();
            $table->char('payload_hash', 64);
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('phone', 40)->default('');
            $table->string('service_key', 40);
            $table->string('requested_window', 180);
            $table->text('message')->nullable();
            $table->string('source', 80)->default('tighten-up-your-locs-site');
            $table->string('status', 24)->default('received');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('consent_at');
            $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->unique()->constrained('booking_requests')->restrictOnDelete();
            $table->string('status', 24);
            $table->unsignedBigInteger('starts_at');
            $table->unsignedBigInteger('ends_at');
            $table->unsignedBigInteger('pending_starts_at')->nullable();
            $table->unsignedBigInteger('pending_ends_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->char('response_token_hash', 64)->nullable();
            $table->string('response_kind', 24)->nullable();
            $table->unsignedBigInteger('response_expires_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
        });
        Schema::create('openings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label', 100);
            $table->unsignedBigInteger('starts_at');
            $table->unsignedBigInteger('ends_at');
            $table->boolean('published')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('booking_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 128)->unique();
            $table->char('payload_hash', 64);
            $table->uuid('entity_id');
            $table->string('action', 40);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('result');
            $table->timestamp('created_at');
            $table->index(['entity_id', 'created_at']);
        });
        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->string('recipient', 254);
            $table->string('template', 60);
            $table->json('payload');
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        foreach (['notification_outbox', 'booking_events', 'openings', 'appointments', 'booking_requests', 'booking_resource_locks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
