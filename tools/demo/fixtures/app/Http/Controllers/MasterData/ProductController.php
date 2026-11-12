<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use Illuminate\Http\Request;

class ProductController
{
    public function store(Request $request)
    {
        $request->validate(['code' => ['required', 'max:10'], 'name' => ['required'], 'unit' => ['required', 'in:pcs,kg,m']]);

        return response()->json(['ok' => true], 201);
    }

    public function importBom(Request $request)
    {
        return response()->json(['imported' => count($request->input('rows', []))], 201);
    }

    public function attach(Request $request)
    {
        return response()->json(['stored' => $request->file('file')?->getClientOriginalName()], 201);
    }
}
