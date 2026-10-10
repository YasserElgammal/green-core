<?php
declare(strict_types=1);

namespace YasserElgammal\Green\Queue\Migrations;

use YasserElgammal\Green\Database\Migrations\Migration;
use YasserElgammal\Green\Database\Schema\Schema;

final class CreateQueueJobsTable extends Migration
{
    public function up(): void
    {
        Schema::create('queue_jobs', function ($table) {
            $table->id();
            $table->string('queue', 255);
            $table->text('payload');
            $table->integer('attempts');
            $table->timestamp('available_at');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['queue', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_jobs');
    }
}
