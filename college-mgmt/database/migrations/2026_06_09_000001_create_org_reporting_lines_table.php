<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('org_reporting_lines', function (Blueprint $table) {
            $table->id();
            $table->string('parent_role', 50);
            $table->string('child_role', 50);
            $table->boolean('can_view_summary')->default(true);
            $table->boolean('can_view_full')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['parent_role', 'child_role']);
        });

        $lines = [
            ['director', 'dean_academics',   true,  true,  1],
            ['director', 'hod',              true,  true,  2],
            ['director', 'program_chair',    true,  true,  3],
            ['director', 'exam_cell',        true,  true,  4],
            ['director', 'accounts_officer', true,  true,  5],
            ['director', 'cmc',              true,  true,  6],
            ['director', 'admin',            true,  false, 7],
            ['dean_academics', 'program_chair', true, true, 1],
            ['dean_academics', 'hod',          true, true, 2],
            ['dean_academics', 'exam_cell',    true, true, 3],
            ['hod',  'program_chair',          true, false, 1],
        ];
        foreach ($lines as [$p, $c, $sum, $full, $sort]) {
            DB::table('org_reporting_lines')->insert([
                'parent_role' => $p, 'child_role' => $c,
                'can_view_summary' => $sum, 'can_view_full' => $full,
                'sort_order' => $sort,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {
        Schema::dropIfExists('org_reporting_lines');
    }
};
