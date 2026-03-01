<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;

class DriverController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $drivers = Driver::with('user')
            ->latest()
            ->paginate(15);

        return view('admin.drivers.index', compact('drivers'));
    }
}
