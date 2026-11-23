<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('username')->nullable()->unique());
        DB::table('users')->select('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['username' => 'user-'.$user->id]);
            }
        });
        Schema::table('users', fn (Blueprint $table) => $table->string('username')->nullable(false)->change());

        DB::table('sessions')->whereNotIn('user_id', DB::table('users')->select('id'))->delete();
        Schema::table('sessions', fn (Blueprint $table) => $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete());
    }

    public function down(): void
    {
        Schema::table('sessions', fn (Blueprint $table) => $table->dropForeign(['user_id']));
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['username']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('username'));
    }
};
