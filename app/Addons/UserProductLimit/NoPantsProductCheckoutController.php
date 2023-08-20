<?php

namespace App\Addons\UserProductLimit;

use Illuminate\Http\Request;
use App\Helpers\ExtensionHelper;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\{Extension, Invoice, OrderProduct, OrderProductConfig, Order, Product, User, Coupon, InvoiceItem};
use Illuminate\Support\Arr;

class NoPantsProductCheckoutController extends Controller
{
    public function pay(Request $request)
    {
        $cart = session('cart');
        if (!$cart) {
            return redirect()->back()->with('error', 'Cart is empty');
        }
        if ($request->has('tos') && config('settings::tos') == 1) {
            $tos = $request->input('tos');
            if ($tos == 'on') {
                $tos = true;
            } else {
                $tos = false;
            }
            if (!$tos) {
                return redirect()->back()->with('error', 'You must agree to the terms of service');
            }
        } else if (config('settings::tos') == 1) {
            return redirect()->back()->with('error', 'You must agree to the terms of service');
        }
        $couponId = session('coupon');
        $coupon;
        if ($couponId) {
            $coupon = Coupon::where('id', $couponId)->first();
        } else {
            $coupon = null;
        }
        $total = 0;
        $products = [];
        foreach ($cart as $product) {
            if ($product->stock_enabled && $product->stock <= 0) {
                return redirect()->back()->with('error', 'Product is out of stock');
            } elseif ($product->stock_enabled && $product->stock < $product->quantity) {
                return redirect()->back()->with('error', 'Product is out of stock');
            }
            if ($coupon) {
                if (isset($coupon->products)) {
                    if (!in_array($product->id, $coupon->products) && !empty($coupon->products)) {
                        $product->discount = 0;
                        continue;
                    } else {
                        if ($coupon->type == 'percent') {
                            $product->discount = $product->price * $coupon->value / 100;
                            $product->discount_fee = $product->setup_fee * $coupon->value / 100;
                        } else {
                            $product->discount = $coupon->value;
                            $product->discount_fee = $coupon->value;
                        }
                    }
                } else {
                    if ($coupon->type == 'percent') {
                        $product->discount = $product->price * $coupon->value / 100;
                        $product->discount_fee = $product->setup_fee * $coupon->value / 100;
                    } else {
                        $product->discount = $coupon->value;
                        $product->discount_fee = $coupon->value;
                    }
                }
            } else {
                $product->discount = 0;
                $product->discount_fee = 0;
            }
            if ($product->setup_fee) {
                $total += ($product->setup_fee + $product->price) * $product->quantity - $product->discount - $product->discount_fee;
            } else {
                $total += $product->price * $product->quantity - $product->discount;
            }
            $products[] = $product;
        }

        $user = User::findOrFail(auth()->user()->id);

        if ($user) {
            // Get the user_limit of the product
            $productLimit = Product::findOrFail($product->id);
            $productLimit = $productLimit->user_limit;
            if ($productLimit !== null && $productLimit !== 0) {
                // Get the order products associated with the user
                $userProducts = $user->orderProducts;
                // Count the instances of the product for the user
                $productInstanceCount = $userProducts->where('product_id', $product->id)->count();
                
                // Check if the product instance count exceeds the user limit
                if ($productInstanceCount >= $productLimit) {
                    // Redirect back with an error message
                    return redirect()->back()->with('error', 'You may only have ' . $productLimit . ' instance of ' . $product->name);
                }
            }
        }

        $order = new Order();
        $order->client = $user->id;
        $order->coupon = $coupon->id ?? null;
        $order->save();

        $invoice = new Invoice();
        $invoice->user_id = $user->id;
        $invoice->order_id = $order->id;
        if ($total == 0) {
            $invoice->status = 'paid';
        } else {
            $invoice->status = 'pending';
        }
        $invoice->save();
        foreach ($products as $product) {
            if ($product->allow_quantity == 1)
                for (
                    $i = 0;
                    $i < $product->quantity;
                    ++$i
                ) {
                    $this->createOrderProduct($order, $product, $invoice, false);
                }
            else if ($product->allow_quantity == 2)
                $this->createOrderProduct($order, $product, $invoice);
            else
                $this->createOrderProduct($order, $product, $invoice);
            if ($product->setup_fee > 0) {
                $invoiceItem = new InvoiceItem();
                $invoiceItem->invoice_id = $invoice->id;
                $invoiceItem->description = $product->name . ' Setup Fee';
                $invoiceItem->total = ($product->setup_fee - $product->discount_fee) * $product->quantity;
                $invoiceItem->save();
            }
        }

        session()->forget('cart');
        session()->forget('coupon');
        if (!config('settings::mail_disabled')) {
            NotificationHelper::sendNewOrderNotification($order, auth()->user());
            if ($total != 0) {
                NotificationHelper::sendNewInvoiceNotification($invoice, auth()->user());
            }
        }
        foreach ($order->products()->get() as $product) {
            $iproduct = Product::where('id', $product->product_id)->first();
            if ($iproduct->stock_enabled) {
                $iproduct->stock = $iproduct->stock - $product->quantity;
                $iproduct->save();
            }
        }
        if ($coupon) {
            $coupon->uses = $coupon->uses + 1;
            $coupon->save();
        }
        if ($total != 0) {
            $products = $invoice->getItemsWithProducts()->products;
            $total = $invoice->getItemsWithProducts()->total;
            if ($total == 0) {
                $invoice->status = 'paid';
                $invoice->save();

                return redirect()->route('clients.invoice.show', $invoice->id);
            }

            if ($request->get('payment_method')) {
                $payment_method = $request->get('payment_method');
                if ($payment_method == 'credits') {
                    $user = User::where('id', auth()->user()->id)->first();
                    if ($user->credits < $total) {
                        return redirect()->route('clients.invoice.show', $invoice->id)->with('error', 'You do not have enough credits');
                    }
                    $user->credits = $user->credits - $total;
                    $user->save();
                    ExtensionHelper::paymentDone($invoice->id);
                    return redirect()->route('clients.invoice.show', $invoice->id)->with('success', 'Payment done');
                }
                $payment_method = ExtensionHelper::getPaymentMethod($payment_method, $total, $products, $invoice->id);
                if ($payment_method) {
                    return redirect($payment_method);
                } else {
                    return redirect()->back()->with('error', 'Payment method not found');
                }
            } else {
                return redirect()->route('clients.invoice.show', $invoice->id);
            }
        }

        return redirect()->route('clients.home')->with('success', 'Order created successfully');
    }

    private function createOrderProduct(Order $order, Product $product, Invoice $invoice, $setQuantity = true)
    {
        $orderProduct = new OrderProduct();
        $orderProduct->order_id = $order->id;
        $orderProduct->product_id = $product->id;
        $orderProduct->quantity = $product->quantity;
        $orderProduct->price = $product->price;
        if ($product->billing_cycle) {
            $orderProduct->billing_cycle = $product->billing_cycle;
            if ($product->billing_cycle == 'monthly') {
                $orderProduct->expiry_date = date('Y-m-d H:i:s', strtotime('+1 month'));
            } elseif ($product->billing_cycle == 'quarterly') {
                $orderProduct->expiry_date = date('Y-m-d H:i:s', strtotime('+3 months'));
            } elseif ($product->billing_cycle == 'semi_annually') {
                $orderProduct->expiry_date = date('Y-m-d H:i:s', strtotime('+6 months'));
            } elseif ($product->billing_cycle == 'annually') {
                $orderProduct->expiry_date = date('Y-m-d H:i:s', strtotime('+1 year'));
            } elseif ($product->billing_cycle == 'biennially') {
                $orderProduct->expiry_date = date('Y-m-d H:i:s', strtotime('+2 years'));
            } elseif ($product->billing_cycle == 'triennially') {
                $orderProduct->expiry_date = date('Y-m-d H:i:s', strtotime('+3 years'));
            }
            $orderProduct->save();
        }

        if ($setQuantity) $orderProduct->quantity = $product->quantity ?? 1;
        else $orderProduct->quantity = 1;
        $orderProduct->save();
        if (isset($product->config)) {
            foreach ($product->config as $key => $value) {
                $orderProductConfig = new OrderProductConfig();
                $orderProductConfig->order_product_id = $orderProduct->id;
                $orderProductConfig->key = $key;
                $orderProductConfig->value = $value;
                $orderProductConfig->save();
            }
        }
        if (isset($product->configurableOptions)) {
            foreach ($product->configurableOptions as $key => $value) {
                $orderProductConfig = new OrderProductConfig();
                $orderProductConfig->order_product_id = $orderProduct->id;
                $orderProductConfig->key = $key;
                $orderProductConfig->value = $value;
                $orderProductConfig->is_configurable_option = true;
                $orderProductConfig->save();
            }
        }
        if ($product->price == 0 || $product->price - $product->discount == 0) {
            $orderProduct->status = 'paid';
            $orderProduct->save();
            ExtensionHelper::createServer($orderProduct);
            return;
        } else {
            $orderProduct->status = 'pending';
            $orderProduct->save();
        }
        $invoiceProduct = new InvoiceItem();
        $invoiceProduct->invoice_id = $invoice->id;
        $invoiceProduct->product_id = $orderProduct->id;
        $invoiceProduct->total = $orderProduct->price * $orderProduct->quantity;
        $description = $orderProduct->billing_cycle ? '(' . now()->format('Y-m-d') . ' - ' . date('Y-m-d', strtotime($orderProduct->expiry_date)) . ')' : '';
        $invoiceProduct->description = $product->name . ' ' . $description;
        $invoiceProduct->save();
    }
}
