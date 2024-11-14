<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissionsToCreate = [
            'users-list' => 'User Listing'
        ];

        DB::table('role_permissions')->where('name','=','users-list')->delete();
        foreach ($permissionsToCreate as $name => $displayName) {
            DB::table('role_permissions')->insertGetId([
                'name'         => $name,
                'display_name' => $displayName,
                'created_at'   => Carbon::now()->toDateTimeString(),
                'updated_at'   => Carbon::now()->toDateTimeString(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role_permissions')->where('name','users-list')->delete();
    }
};
