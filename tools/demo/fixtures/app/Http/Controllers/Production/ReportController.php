<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use Illuminate\Support\Facades\DB;

class ReportController
{
    public function output()
    {
        foreach (range(1, 6) as $i) {
            DB::select('select count(*) from users where id > ?', [$i]);
        }
        usleep(320000);

        return response()->json(['data' => ['shift' => 'A', 'output' => 1250]]);
    }
}
