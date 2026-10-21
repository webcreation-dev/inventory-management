@if(empty($recentOrders))
    <div class="p-8 text-center text-sm text-slate-500">{{ __('No orders found yet.') }}</div>
@else
    @php
        $paymentTone = [
            'Paid' => 'bg-emerald-50 text-emerald-700',
            'Partial' => 'bg-amber-50 text-amber-700',
            'Unpaid' => 'bg-rose-50 text-rose-700',
        ];
        $orderTone = [
            'Completed' => 'bg-emerald-50 text-emerald-700',
            'Delivered' => 'bg-emerald-50 text-emerald-700',
            'Confirmed' => 'bg-sky-50 text-sky-700',
            'On The Way' => 'bg-indigo-50 text-indigo-700',
            'Pickup' => 'bg-sky-50 text-sky-700',
            'Pending' => 'bg-amber-50 text-amber-700',
            'Processing' => 'bg-slate-100 text-slate-700',
            'Rejected' => 'bg-rose-50 text-rose-700',
            'Cancelled' => 'bg-slate-100 text-slate-700',
            'Returned' => 'bg-slate-100 text-slate-700',
        ];
    @endphp
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold text-slate-600">{{ __('Invoice') }}</th>
                    <th class="px-5 py-3 text-left font-semibold text-slate-600">{{ __('Date') }}</th>
                    <th class="px-5 py-3 text-left font-semibold text-slate-600">{{ __('Payment Status') }}</th>
                    <th class="px-5 py-3 text-left font-semibold text-slate-600">{{ __('Order Status') }}</th>
                    <th class="px-5 py-3 text-right font-semibold text-slate-600">{{ __('Amount') }}</th>
                    <th class="px-5 py-3 text-right font-semibold text-slate-600">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($recentOrders as $order)
                    @php
                        $pay = $order['payment_status'] ?? ($order['status'] ?? '-');
                        $ord = $order['order_status'] ?? '-';
                        $canCancel = !empty($order['can_cancel']);
                    @endphp
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-900">{{ $order['invoice'] }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $order['date'] }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $paymentTone[$pay] ?? 'bg-slate-100 text-slate-700' }}">{{ __($pay) }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $orderTone[$ord] ?? 'bg-slate-100 text-slate-700' }}">{{ __($ord) }}</span>
                        </td>
                        <td class="px-5 py-3 text-right font-semibold text-slate-900">
                            {{ function_exists('formatAmount') ? formatAmount($order['amount']) : ('$' . number_format((float) $order['amount'], 2)) }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if($canCancel && !empty($order['encrypted_id']))
                                <form method="POST" action="{{ route('ecommerce.customer-panel.orders.cancel', $order['encrypted_id']) }}" class="inline"
                                      onsubmit="return confirm(@json(__('Are you sure you want to cancel this order?')));">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100"
                                            title="{{ __('Cancel Order') }}"
                                            aria-label="{{ __('Cancel Order') }}">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
