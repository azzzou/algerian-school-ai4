<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for the AI leads database (sqlite_leads connection).
 * Ensures the leads table has all columns required by the structured
 * output Pydantic models (student_name, phone_number, branch_or_level,
 * lead_score, etc.).
 *
 * NOTE: This migration targets the external ai_engine/leads.db via the
 * 'sqlite_leads' connection defined in config/database.php.
 */
class CreateLeadsTable extends Migration
{
    /**
     * The database connection to use for this migration.
     */
    protected $connection = 'sqlite_leads';

    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::connection($this->connection)->create('leads', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('created_at');
            $table->string('source')->default('simulation');
            $table->string('conversation_id')->nullable();
            $table->text('raw_message')->nullable();
            $table->string('student_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('branch_or_level')->nullable();
            $table->string('lead_score')->default('COLD');
            $table->string('level')->nullable();
            $table->string('filiere')->nullable();
            $table->string('subject')->nullable();
            $table->text('ai_reply')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('leads');
    }
}
