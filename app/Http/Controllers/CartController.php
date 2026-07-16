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
            'redirect_to' => ['nullable', 'string'],
        ]);

        $product = Product::findOrFail($request->integer('product_id'));
        $cart = $this->cart->getOrCreate($request->user());

        try {
            $this->cart->add($cart, $product);
        } catch (CartException $e) {
            return $this->redirectTo($request)->withErrors(['cart' => $e->getMessage()]);
        }

        return $this->redirectTo($request)->with('success', 'Added to cart');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);

        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        try {
            $this->cart->updateQuantity($cartItem, $request->integer('quantity'));
        } catch (CartException $e) {
            return $this->redirectTo($request)->withErrors(['cart' => $e->getMessage()]);
        }

        return $this->redirectTo($request);
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);

        $request->validate([
            'redirect_to' => ['nullable', 'string'],
        ]);

        $this->cart->remove($cartItem);

        return $this->redirectTo($request);
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->validate([
            'redirect_to' => ['nullable', 'string'],
        ]);

        $cart = $this->cart->getOrCreate($request->user());
        $this->cart->clear($cart);

        return $this->redirectTo($request);
    }

    /**
     * Redirect to the page the request came from, using an explicit path from
     * the client rather than back()/Referer — Inertia's SPA navigation doesn't
     * reliably update the session's previous-URL tracking or send a Referer
     * header, so back() can silently land on a stale, unrelated page.
     */
    private function redirectTo(Request $request): RedirectResponse
    {
        $path = $request->string('redirect_to')->toString();

        $isSafeRelativePath = $path !== ''
            && str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ! str_contains($path, '\\')
            && parse_url($path, PHP_URL_HOST) === null;

        return redirect($isSafeRelativePath ? $path : route('cart'));
    }
}
