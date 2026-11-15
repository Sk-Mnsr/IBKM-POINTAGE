<?php

namespace App\Http\Controllers;

use App\Enums\CustomerReviewRating;
use App\Models\CustomerReview;
use App\Models\Kiosk;
use App\Support\ShopContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerReviewController extends Controller
{
    public function index(Request $request, ShopContext $shops): Response
    {
        $shopIds = $shops->scopeIds();
        $from = $this->date($request->string('du')->toString()) ?? CarbonImmutable::today()->subDays(29);
        $to = $this->date($request->string('au')->toString()) ?? CarbonImmutable::today();
        $kioskId = $request->integer('borne') ?: null;

        if ($from->isAfter($to)) {
            [$from, $to] = [$to, $from];
        }

        $base = CustomerReview::query()
            ->whereIn('shop_id', $shopIds)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($kioskId, fn ($query) => $query->where('kiosk_id', $kioskId));

        $countsByRating = (clone $base)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $total = (int) $countsByRating->sum();

        $reviews = (clone $base)
            ->with(['kiosk:id,name,public_id', 'shop:id,name'])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Reviews/Index', [
            'reviews' => [
                'data' => $reviews->getCollection()->map(fn (CustomerReview $review): array => [
                    'id' => $review->id,
                    'rating' => $review->rating->toArray(),
                    'comment' => $review->comment,
                    'kiosk' => $review->kiosk?->name,
                    'shop' => $review->shop?->name,
                    'at' => $review->created_at?->format('d/m/Y H:i'),
                    'dayLabel' => ucfirst($review->created_at?->translatedFormat('l j F') ?? ''),
                ])->all(),
                'links' => $reviews->linkCollection()->toArray(),
                'total' => $reviews->total(),
                'from' => $reviews->firstItem(),
                'to' => $reviews->lastItem(),
            ],
            'stats' => [
                'total' => $total,
                'verySatisfied' => (int) ($countsByRating[CustomerReviewRating::VerySatisfied->value] ?? 0),
                'satisfied' => (int) ($countsByRating[CustomerReviewRating::Satisfied->value] ?? 0),
                'unsatisfied' => (int) ($countsByRating[CustomerReviewRating::Unsatisfied->value] ?? 0),
                'satisfactionRate' => $total === 0
                    ? null
                    : (int) round((((int) ($countsByRating[CustomerReviewRating::VerySatisfied->value] ?? 0)
                        + (int) ($countsByRating[CustomerReviewRating::Satisfied->value] ?? 0)) / $total) * 100),
            ],
            'byKiosk' => $this->totalsByKiosk($shopIds, $from, $to),
            'filters' => [
                'du' => $from->toDateString(),
                'au' => $to->toDateString(),
                'borne' => $kioskId,
            ],
            'kiosks' => Kiosk::query()
                ->whereIn('shop_id', $shopIds)
                ->orderBy('name')
                ->get(['id', 'name', 'shop_id'])
                ->map(fn (Kiosk $kiosk): array => ['id' => $kiosk->id, 'name' => $kiosk->name])
                ->all(),
            'showShop' => $shops->isMultiShop(),
            'periodLabel' => 'du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y'),
        ]);
    }

    /**
     * @param  list<int>  $shopIds
     * @return list<array{kioskId: int, name: string, total: int}>
     */
    private function totalsByKiosk(array $shopIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = CustomerReview::query()
            ->whereIn('shop_id', $shopIds)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('kiosk_id, count(*) as aggregate')
            ->groupBy('kiosk_id')
            ->orderByDesc('aggregate')
            ->get();

        $names = Kiosk::query()
            ->whereIn('id', $rows->pluck('kiosk_id'))
            ->pluck('name', 'id');

        return $rows->map(fn (CustomerReview $row): array => [
            'kioskId' => (int) $row->kiosk_id,
            'name' => (string) ($names[$row->kiosk_id] ?? 'Borne'),
            'total' => (int) $row->aggregate,
        ])->all();
    }

    private function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
