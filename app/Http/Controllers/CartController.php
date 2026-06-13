<?php

namespace App\Http\Controllers;

use App\Exceptions\CartException;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(Request $request): Response
    {
        $cart = $this->cart->getOrCreate($request->user());

        $cart->loadMissing('items.product');

        $allItems = $cart->items;

        $availableItems = $allItems->filter(fn ($item) => $item->product !== null)->values();
        $unavailableItems = $allItems->filter(fn ($item) => $item->product === null)->values();

        $cart->setRelation('items', $availableItems);

        return Inertia::render('cart/index', [
            'cart' => $cart,
            'unavailableItems' => $unavailableItems,
            'total' => $this->cart->total($cart),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::findOrFail($request->integer('product_id'));
        $cart = $this->cart->getOrCreate($request->user());

        try {
            $this->cart->add($cart, $product);
        } catch (CartException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return back()->with('success', 'Added to cart');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);

        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->cart->updateQuantity($cartItem, $request->integer('quantity'));
        } catch (CartException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return back();
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);

        $this->cart->remove($cartItem);

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        $cart = $this->cart->getOrCreate($request->user());
        $this->cart->clear($cart);

        return back();
    }
}
