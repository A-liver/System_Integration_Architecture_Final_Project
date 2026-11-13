<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->unsignedTinyInteger('units');
            $table->string('schedule');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('seats_taken')->default(0);
            $table->timestamps();
        });

        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_id')->constrained('courses')->cascadeOnDelete();
            $table->primary(['course_id', 'prerequisite_id']);
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('student_number')->index();
            $table->string('student_name');
            $table->string('term_code');
            $table->string('status')->index();
            $table->unsignedInteger('units');
            $table->unsignedBigInteger('tuition_cents');
            $table->unsignedBigInteger('misc_fee_cents');
            $table->unsignedBigInteger('total_cents');
            $table->string('currency', 3);
            $table->timestamps();
        });

        Schema::create('course_registration', function (Blueprint $table) {
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained();
            $table->primary(['registration_id', 'course_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_order_id')->unique();
            $table->string('approval_url', 1024);
            $table->string('payment_reference')->nullable()->unique();
            $table->string('status')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('last_message')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('enrollment_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('payment_reference')->unique();
            $table->string('status')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('last_error')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('student_number')->index();
            $table->foreignId('registration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('body');
            $table->timestamps();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('component')->index();
            $table->string('action');
            $table->string('outcome')->index();
            $table->string('message')->nullable();
            $table->string('reference')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
        });
    }

    public function down(): void
    {
        foreach (['settings', 'integration_logs', 'notices', 'enrollment_updates', 'payments', 'course_registration', 'registrations', 'course_prerequisites', 'courses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
