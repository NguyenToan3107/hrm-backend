<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct()
    {
    }

    public function index() {
        $departments = Department::select('id', 'name')
            ->where('is_delete', 0)
            ->get();

        return response()->json([
            'code'    => OK,
            'message' => '',
            'data'    => $departments
        ], SUCCESS);
    }
}
