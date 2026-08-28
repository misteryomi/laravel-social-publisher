<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_queue_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Where the post originated — e.g. 'policy_update', 'event', 'product'.
            // App-specific values; no constraint enforced here.
            $table->string('source')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();

            $table->string('platform');      // x, linkedin, instagram, facebook, tiktok
            $table->string('content_type'); // app-defined — policy, jobs, event, etc.
            $table->text('copy');           // editable post body (always the current version)
            $table->json('card_config')->nullable();       // {headline, highlight?, subtitle?, ...}
            $table->string('card_image_url')->nullable();  // rendered card stored on disk/S3

            // draft → approved → queued → published / dismissed
            $table->string('status')->default('draft')->index();
            $table->string('buffer_post_id')->nullable();
            $table->string('suggested_timing')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_queue_items');
    }
};
