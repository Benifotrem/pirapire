<?php

namespace App\Http\Controllers;

use App\Models\LedAdSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public self-service form ("¿Tu negocio acepta Bitcoin en Paraguay?") for
 * the header LED ticker — reachable from the placeholder ad's link.
 * Submissions land as `pending` and only appear on the ticker once an
 * admin approves them (App\Filament\Resources\LedAdSubmissionResource).
 */
class LedAdSubmissionController extends Controller
{
    public function show(): View
    {
        return view('led-ad-submission');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'category' => 'required|in:cafeteria,restaurante,tienda,hotel,servicios,otro',
            'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'business_hours' => 'nullable|string|max:255',
            'accepts_lightning' => 'nullable|boolean',
            'accepts_onchain' => 'nullable|boolean',
            'message' => 'required|string|max:200',
            'url' => 'required|url|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ]);

        $validated['accepts_lightning'] = $request->boolean('accepts_lightning');
        $validated['accepts_onchain'] = $request->boolean('accepts_onchain');

        LedAdSubmission::create($validated);

        return back()->with('status', '¡Gracias! Tu comercio quedó enviado para revisión. Te contactaremos si necesitamos algo más antes de publicarlo.');
    }
}
