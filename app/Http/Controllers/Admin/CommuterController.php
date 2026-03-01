<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\DiscountTypes;
use Illuminate\Http\Request;

class CommuterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = Commuter::with('user', 'discount.classificationType');

        if ($search = $request->input('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($classification = $request->input('classification')) {
            $query->whereHas('discount.classificationType', fn ($q) => $q->where('classification_name', $classification));
        }

        $commuters = $query->latest()->paginate(15)->withQueryString();

        $classifications = DiscountTypes::pluck('classification_name')->unique();

        if ($request->ajax()) {
            return response()->json([
                'rows'       => view('admin.commuters._rows', compact('commuters'))->render(),
                'pagination' => $commuters->hasPages() ? (string) $commuters->links() : '',
                'total'      => $commuters->total(),
            ]);
        }

        return view('admin.commuters.index', compact('commuters', 'classifications'));
    }
}
