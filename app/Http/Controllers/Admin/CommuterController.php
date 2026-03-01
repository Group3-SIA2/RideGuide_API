<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commuter;

class CommuterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $commuters = Commuter::with('user', 'discount.classificationType')
            ->latest()
            ->paginate(15);

        return view('admin.commuters.index', compact('commuters'));
    }
}
