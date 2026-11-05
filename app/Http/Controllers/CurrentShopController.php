<?php

namespace App\Http\Controllers;

use App\Support\ShopContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentShopController extends Controller
{
    public function update(Request $request, ShopContext $shops): RedirectResponse
    {
        $validated = $request->validate([
            'shop_id' => ['nullable', 'integer'],
        ]);

        $shops->select(isset($validated['shop_id']) ? (int) $validated['shop_id'] : null);

        return back();
    }
}
