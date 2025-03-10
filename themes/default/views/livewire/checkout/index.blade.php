<div>
    @empty(!$this->products)
        <div class="grid grid-cols-12 gap-4">
            <div class="lg:col-span-8 col-span-12">
                <div class="content-box !p-0 overflow-hidden">
                    <h2 class="text-xl font-bold px-6 pt-5 pb-2">{{ __('Order overview') }}</h2>
                    <table class="w-full">
                        <thead class="border-b-2 border-secondary-200 dark:border-secondary-50 text-secondary-600">
                            <tr>
                                <th scope="col" class="text-start pl-6 py-2 text-sm font-normal">
                                    {{ __('Product') }}
                                </th>
                                <th scope="col" class="text-start py-2 text-sm font-normal">
                                    {{ __('Quantity') }}
                                </th>
                                <th scope="col" class="text-start pr-6 py-2 text-sm font-normal">
                                    {{ __('Price') }}
                                </th>
                                <th scope="col" class="text-start pr-6 py-2 text-sm font-normal">
                                    {{ __('Remove') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->products as $product)
                                <tr
                                    class="{{ $loop->last ? '' : 'border-b-2 border-secondary-200 dark:border-secondary-50' }}">
                                    <td class="pl-6 py-3">
                                        <div class="flex">
                                            @if ($product->image !== 'null')
                                                <img src="{{ $product->image }}" alt="{{ $product->name }}"
                                                    class="w-8 h-8 md:w-12 md:h-12 my-auto rounded-md"
                                                    onerror="removeElement(this);">
                                            @endif
                                            <strong class="ml-3 my-auto">{{ ucfirst($product->name) }}</strong>
                                        </div>
                                        @error('product.' . $product->id)
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td class="py-3">
                                        @if ($product->allow_quantity == 1 || $product->allow_quantity == 2)
                                            <input type="number" name="quantity"
                                                wire:change="updateQuantity({{ $product->id }}, $event.target.value)"
                                                wire:model="quantity" value="{{ $product->quantity }}"
                                                class="w-16 border border-secondary-200 dark:border-secondary-50 dark:bg-secondary-200 rounded-md px-2 py-1 text-sm text-center"
                                                min="1" max="100" />
                                        @else
                                            {{ $product->quantity }}
                                        @endif
                                    </td>
                                    <td class="py-3">
                                        @if ($product->discount)
                                            <span class="text-red-500 line-through"><x-money :amount="$product->price" /></span>
                                            <x-money :amount="round($product->price - $product->discount, 2)" />
                                        @else
                                            <x-money :amount="$product->price" :showFree="true" />
                                        @endif
                                        @if ($product->quantity > 1 && $product->price > 0)
                                            {{ __('each') }}
                                        @endif
                                    </td>
                                    <td class="py-3 pr-6" wire:click="removeProduct({{ $product->id }})">
                                        <button type="submit" class="button button-danger-outline">
                                            <i class="ri-delete-bin-2-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="lg:col-span-4 col-span-12">
                <div class="content-box">
                    @if (empty($coupon))
                        <div class="flex flex-row items-start gap-x-2 place-content-stretch">
                            <div class="flex flex-col w-full">
                                <x-input type="text" placeholder="{{ __('Coupon') }}" name="couponCode"
                                    wire:model="couponCode" icon="ri-coupon-2-line" class="w-full" />
                            </div>
                            <button type="submit" class="button button-primary" wire:click="validateCoupon">
                                {{ __('Validate') }}
                            </button>
                        </div>
                    @else
                        <div class="flex items-center justify-between">
                            <div>
                                {{ __('Coupon:') }}
                                <span class="text-secondary-900 font-semibold">{{ $coupon->code }}</span>
                            </div>
                            <button class="button button-danger" wire:click="removeCoupon">
                                <i class="ri-delete-bin-2-line"></i>
                            </button>
                        </div>
                    @endif
                    <hr class="my-4 border-secondary-300">
                    @foreach ($this->products as $product)
                        @if ($product->price > 0)
                            <span class="text-sm uppercase font-light text-gray-500 -mb-3">
                                {{ ucfirst($product->name) }}
                            </span>
                            <div class="flex flex-row items-center justify-between -mt-1">
                                <div class="flex flex-row items-center">
                                    <span>
                                        {{ ucfirst($product->billing_cycle) ?? 'One time' }}
                                    </span>
                                </div>
                                <div class="flex flex-col">
                                    <span>
                                        @php
                                            if ($product->quantity > 1) {
                                                $quantity = $product->quantity . ' x';
                                            } else {
                                                $quantity = '';
                                            }
                                        @endphp
                                        @if ($product->discount)
                                            {{ $quantity }}
                                            <x-money :amount="round($product->price - $product->discount, 2)" />
                                        @else
                                            {{ $quantity }}
                                            <x-money :amount="$product->price" />
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @endif
                        @if ($product->setup_fee > 0)
                            <div class="flex flex-row items-center justify-between">
                                <div class="flex flex-row items-center">
                                    <span class="text-lg">
                                        {{ __('Setup fee') }}
                                    </span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-lg">
                                        {{ $quantity }}
                                        <x-money :amount="$product->setup_fee - $product->discount_fee" />
                                    </span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                    @if (!empty($discount))
                        <div class="flex flex-row items-center justify-between mt-3">
                            <div class="flex flex-row items-center">
                                <span>{{ __('Discount') }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span>{{ config('settings::currency_sign') }}
                                    {{ round($discount, 2) }}
                                    @if ($coupon->type == 'percent')
                                        ({{ $coupon->value }}%)
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endif
                    <div class="flex flex-row items-center justify-between mt-2">
                        <div class="flex flex-row items-center">
                            <span class="text-lg font-bold">{{ __('Total Today') }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-lg font-bold">{{ config('settings::currency_sign') }}
                                @if (!empty($discount))
                                    {{ number_format(round($total - $discount, 2), 2) }}
                                @else
                                    {{ number_format($total, 2) }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <hr class="my-4 border-secondary-300">
                    <form wire:submit.prevent="pay">
                        <div class="flex flex-col">
                            <label for="payment_method"
                                class="text-sm text-secondary-600">{{ __('Payment method') }}</label>
                            <select id="payment_method" name="payment_method" autocomplete="payment_method"
                                wire:model.live="payment_method"
                                class="py-2 bg-secondary-200 text-secondary-800 font-medium rounded-md placeholder-secondary-500 outline-none w-full border focus:ring-2 focus:ring-offset-2 ring-offset-secondary-50 dark:ring-offset-secondary-100 duration-300 border-secondary-300 focus:border-secondary-400 focus:ring-primary-400">
                                @foreach (App\Models\Extension::where('type', 'gateway')->where('enabled', true)->get() as $gateway)
                                    <option value="{{ $gateway->id }}">
                                        {{ isset($gateway->display_name) ? $gateway->display_name : $gateway->name }}
                                    </option>
                                @endforeach
                                @if (config('settings::credits') && auth()->user() && auth()->user()->credits > 0)
                                    <option value="credits">
                                        {{ __('Pay with credits') }}
                                    </option>
                                @endif
                            </select>
                        </div>
                        @if (config('settings::tos'))
                            <div class="items-center p-1">
                                @php
                                $tos = "I agree to the <a href='" . route('tos') . "' class='text-blue-500 hover:text-blue-600'>terms of service</a>"; @endphp <x-input id="tos" type="checkbox" name="tos" required
                                    :label="$tos" wire:model="tos" />
                            </div>
                        @endif
                        <div class="flex justify-end mt-4">
                            <button class="button button-primary" type="submit">
                                <span wire:loading wire:target="pay">
                                    <i class="ri-loader-4-line animate-spin"></i>
                                </span>
                                <span wire:loading.remove wire:target="pay">
                                    {{ __('Checkout') }}
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="text-center">
            <p>{{ __('There are no products in your cart') }} </p>
        </div>
    @endempty
</div>
