<?php

namespace App\Http\Controllers;

use App\Enums\CustomerReviewRating;
use App\Http\Requests\StoreCustomerReviewRequest;
use App\Models\CustomerReview;
use App\Models\Kiosk;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer satisfaction form: opened from the tablet button or by scanning the avis QR.
 * Public (no pairing) so a client phone can leave a review after scanning.
 */
class KioskReviewController extends Controller
{
    public function show(Kiosk $kiosk): Response
    {
        abort_unless($kiosk->isOpen(), 404);

        return Inertia::render('Kiosk/Reviews', [
            'kioskName' => $kiosk->name,
            'shopName' => $kiosk->shop?->name,
            'kioskUrl' => route('kiosk.show', $kiosk),
            'submitUrl' => route('kiosk.reviews.store', $kiosk),
            'question' => 'Comment s’est passée votre visite ?',
            'ratings' => array_map(
                fn (CustomerReviewRating $rating): array => $rating->toArray(),
                CustomerReviewRating::cases(),
            ),
        ]);
    }

    public function store(StoreCustomerReviewRequest $request, Kiosk $kiosk): JsonResponse
    {
        abort_unless($kiosk->isOpen(), 404);

        CustomerReview::query()->create([
            'shop_id' => $kiosk->shop_id,
            'kiosk_id' => $kiosk->id,
            'rating' => CustomerReviewRating::from($request->string('rating')->toString()),
            'comment' => $request->validated('comment'),
        ]);

        return response()->json([
            'message' => 'Merci pour votre avis.',
        ], 201);
    }
}
