<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MembershipPlanRequest;
use App\Models\MembershipPlan;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MembershipPlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/MembershipPlans', ['plans' => MembershipPlan::with('product')->orderBy('id')->get()]);
    }

    public function store(MembershipPlanRequest $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(MembershipPlanRequest $request, MembershipPlan $membershipPlan): RedirectResponse
    {
        return $this->save($request, $membershipPlan);
    }

    private function save(MembershipPlanRequest $request, ?MembershipPlan $plan): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($plan, $data) {
            $product = $plan?->product_id ? Product::whereKey($plan->product_id)->lockForUpdate()->firstOrFail() : new Product([
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(8)), 'sku' => 'VIP-'.Str::upper(Str::random(12)),
                'type' => 'membership', 'stock' => 0, 'weight_grams' => 0,
            ]);
            $product->fill(['name' => $data['name'], 'price_sen' => $data['price_sen'], 'short_description' => $data['description'] ?? '',
                'status' => $data['is_active'] ? 'active' : 'inactive', 'sale_price_sen' => null]);
            $product->save();
            $plan ??= new MembershipPlan;
            $plan->fill([
                'name' => $data['name'], 'price_sen' => $data['price_sen'], 'duration_months' => $data['duration_months'],
                'benefits' => $data['benefits'], 'is_active' => $data['is_active'], 'product_id' => $product->id,
            ])->save();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Membership plan saved.']);

        return back();
    }
}
