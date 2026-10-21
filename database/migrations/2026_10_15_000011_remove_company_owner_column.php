<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('companies', 'owner_user_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropConstrainedForeignId('owner_user_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('companies', 'owner_user_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }
};
