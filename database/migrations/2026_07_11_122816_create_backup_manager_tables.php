<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->connection());

        $schema->create('backup_manager_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->index();
            $table->unsignedBigInteger('change_sequence')->default(0);
            $table->unsignedBigInteger('backed_up_sequence')->default(0);
            $table->timestamp('last_changed_at')->nullable()->index();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('last_successful_backup_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        $schema->create('backup_manager_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_key')->index();
            $table->string('destination')->index();
            $table->string('status')->index();
            $table->unsignedBigInteger('covered_sequence')->nullable();
            $table->string('artifact_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection());
        $schema->dropIfExists('backup_manager_runs');
        $schema->dropIfExists('backup_manager_sources');
    }

    private function connection(): string
    {
        return (string) (config('backup-manager.state_connection') ?: config('database.default'));
    }
};
