<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Database\Migrations;

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bunting's tables. Pennant's resolved values live in `bunting_features` — Pennant's
 * own schema under a Bunting-owned name, so an app that also runs Pennant's
 * `features` migration never shares (or loses) rows with Bunting.
 */
class Install extends Migration
{
    public function up(): void
    {
        // One row per flag. Definition columns mirror project config (`bunting.flags.<uid>`);
        // the rollout columns are runtime state, so flags can be flipped where admin changes are off.
        Schema::create('bunting_flags', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('public')->default(false);
            $table->json('uriPatterns')->nullable();
            $table->string('mode', 8)->default('off');
            $table->json('siteModes')->nullable();
            $table->json('groupUids')->nullable();
            $table->unsignedTinyInteger('percentage')->default(0);
            $table->timestamp('lastCheckedAt')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->unique();
        });

        // Per-user overrides: beat the flag's rules on every site.
        Schema::create('bunting_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flagId')->constrained('bunting_flags')->cascadeOnDelete();
            // Craft's users.id is a signed int, so no foreignId().
            $table->integer('userId');
            $table->foreign('userId')->references('id')->on(Table::USERS)->cascadeOnDelete();
            $table->boolean('value');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->unique(['flagId', 'userId']);
        });

        // Who changed what. Kept when a flag or the acting user is deleted.
        Schema::create('bunting_audit', function (Blueprint $table) {
            $table->id();
            $table->char('flagUid', 36)->nullable()->index();
            $table->string('flagHandle', 64)->index();
            $table->string('action', 32);
            $table->integer('userId')->nullable();
            $table->foreign('userId')->references('id')->on(Table::USERS)->nullOnDelete();
            $table->string('userName')->nullable();
            $table->json('changes')->nullable();
            $table->dateTime('dateCreated')->index();
        });

        // Pennant's schema (laravel/pennant create_features_table), Bunting's store.
        Schema::create('bunting_features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope');
            $table->text('value');
            $table->timestamps();
            $table->unique(['name', 'scope']);
        });
    }

    public function down(): void
    {
        // Flags are defined in project config; leave nothing behind for a reinstall to trip on.
        ProjectConfig::remove('bunting');

        Schema::dropIfExists('bunting_features');
        Schema::dropIfExists('bunting_audit');
        Schema::dropIfExists('bunting_overrides');
        Schema::dropIfExists('bunting_flags');
    }
}
