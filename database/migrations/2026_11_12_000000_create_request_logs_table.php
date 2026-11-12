<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        $connection = Config::get('request-log.storage.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    public function up(): void
    {
        $table = Config::string('request-log.storage.table');

        Schema::connection($this->getConnection())->create($table, static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 16);
            $table->string('reason', 16);
            $table->string('method', 16);
            $table->text('url');
            $table->string('path', 255);
            $table->string('route_uri', 255)->nullable();
            $table->string('route_name', 255)->nullable();
            $table->string('action', 255)->nullable();
            $table->string('subject', 255)->nullable();
            $table->unsignedSmallInteger('status');
            $table->string('user_id', 64)->nullable();
            $table->string('tenant_id', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('hostname', 255)->nullable();
            $table->string('revision', 64)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('memory_peak_kb');
            $table->unsignedInteger('query_count');
            $table->unsignedInteger('query_time_ms');
            $table->json('queries')->nullable();
            $table->text('request_headers')->nullable();
            $table->longText('request_body')->nullable();
            $table->unsignedBigInteger('request_bytes');
            $table->boolean('request_truncated');
            $table->string('response_kind', 16);
            $table->text('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedBigInteger('response_bytes');
            $table->boolean('response_truncated');
            $table->string('exception_class', 255)->nullable();
            $table->text('exception_message')->nullable();
            $table->string('exception_location', 512)->nullable();
            $table->string('exception_hash', 32)->nullable();
            $table->text('exception_snippet')->nullable();
            $table->json('exception_trace')->nullable();
            $table->text('context')->nullable();
            $table->timestampTz('created_at');

            $table->index('created_at');
            $table->index('reference');
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['subject', 'created_at']);
            $table->index(['exception_hash', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(Config::string('request-log.storage.table'));
    }
};
