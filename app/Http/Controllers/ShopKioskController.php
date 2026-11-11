<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKioskRequest;
use App\Http\Requests\UpdateKioskRequest;
use App\Models\Kiosk;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ShopKioskController extends Controller
{
    public function store(StoreKioskRequest $request, Shop $shop): RedirectResponse
    {
        $kiosk = $shop->kiosks()->create([
            'name' => $request->string('name')->toString(),
            'public_id' => (string) Str::ulid(),
            'secret' => Str::random(40),
        ]);

        return back()->with('status', "Borne {$kiosk->name} créée. Ouvrez-la sur sa tablette puis appairez-la.");
    }

    /**
     * Deactivating a kiosk stops its QR code at once; its punches stay in the history.
     */
    public function update(UpdateKioskRequest $request, Shop $shop, Kiosk $kiosk): RedirectResponse
    {
        $kiosk->update([
            'name' => $request->string('name')->toString(),
            'is_active' => $request->boolean('is_active', $kiosk->is_active),
        ]);

        return back()->with('status', $kiosk->is_active ? 'Borne enregistrée.' : "La borne {$kiosk->name} est désactivée.");
    }
}
