<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function __construct()
    {
    }

    public function index() {
        $positions = Position::select('id', 'name')
            ->where('is_delete', 0)
            ->get();

        return response()->json([
            'code'    => OK,
            'message' => '',
            'data'    => $positions
        ], SUCCESS);
    }
}
