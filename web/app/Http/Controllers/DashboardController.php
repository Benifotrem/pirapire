<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $customer = Auth::guard('customer')->user();

        return view('dashboard', [
            'customer' => $customer,
            'alerts' => $customer->alerts()->latest()->get(),
            'isVip' => $customer->isVip(),
        ]);
    }

    public function storeAlert(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => 'required|in:PYG,USD',
            'order_type' => 'required|in:BUY,SELL,ANY',
            'min_amount' => 'nullable|integer|min:0',
            'max_amount' => 'nullable|integer|gte:min_amount',
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => 'string|max:64',
        ]);

        Auth::guard('customer')->user()->alerts()->create($validated);

        return back()->with('status', 'Alerta creada.');
    }

    public function toggleAlert(Alert $alert): RedirectResponse
    {
        abort_unless($alert->customer_id === Auth::guard('customer')->id(), 403);

        $alert->update(['is_active' => ! $alert->is_active]);

        return back()->with('status', 'Alerta actualizada.');
    }

    public function destroyAlert(Alert $alert): RedirectResponse
    {
        abort_unless($alert->customer_id === Auth::guard('customer')->id(), 403);

        $alert->delete();

        return back()->with('status', 'Alerta eliminada.');
    }
}
