<?php

namespace App\Addons\UserProductLimit;

use App\Helpers\ExtensionHelper;
use App\Models\Product;
use App\Models\Category;
use App\Models\Extension;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\ProductSetting;
use App\Http\Controllers\Controller;
use App\Models\OrderProduct;
use App\Models\ProductPrice;
use Illuminate\View\View;

class NoPantsProductController extends Controller
{
    public function update(Request $request, Product $product)
    {
        $data = request()->validate([
            'name' => 'required',
            'description' => 'required|string|min:10',
            'category_id' => 'required|integer',
            'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:5242',
            'stock' => 'integer|required_if:stock_enabled,true',
            'stock_enabled' => 'boolean',
            'user_limit' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('image') && !$request->get('no_image')) {
            $imageName = time() . '-' . $product->id . '.' . $request->image->extension();
            $request->image->move(public_path('images'), $imageName);
            $data['image'] = '/images/' . $imageName;
            if (file_exists(public_path() . $product->image)) {
                $image = unlink(public_path() . $product->image);
                if (!$image) {
                    error_log('Failed to delete image: ' . public_path() . $product->image);
                }
            }
        }
        $product->stock_enabled = $request->get('stock_enabled') ?? false;

        if ($request->get('no_image')) {
            $data['image'] = 'null';
        }
        $product->update($data);

        return redirect()->route('admin.products.edit', $product->id)->with('success', 'Product updated successfully');
    }
}
