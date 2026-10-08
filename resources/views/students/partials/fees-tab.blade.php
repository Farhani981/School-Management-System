@props(['student', 'feeData' => null])

@php
    $calc = $feeData['calculation'] ?? null;
    $feeVouchers = $feeData['vouchers'] ?? collect();
    $activeVouchers = $feeVouchers->whereNull('cancelled_at');
    $totalAmount = $activeVouchers->sum('total_cents') / 100;
    $totalFine = $activeVouchers->sum(fn ($voucher) => app(\App\Services\FeeBillingService::class)->fine($voucher)) / 100;
    $totalPaid = $activeVouchers->sum(fn ($voucher) => $voucher->payments->whereNull('reversed_at')->sum('amount_cents')) / 100;
    $totalDue = $activeVouchers->sum(fn ($voucher) => $voucher->balance_cents) / 100;
@endphp

<div class="space-y-6">
    <!-- Top 4 EduDash Stat Cards (Screenshot 3) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Amount -->
        <div class="bg-white rounded-xl p-5 border border-slate-200/80 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.05)] flex items-center justify-between">
            <div>
                <span class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight block">Rs. {{ number_format($totalAmount) }}</span>
                <span class="text-xs font-semibold text-slate-500 mt-1 block">Total Amount</span>
            </div>
            <div class="w-12 h-12 rounded-full bg-sky-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-sky-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <!-- Total Fine -->
        <div class="bg-white rounded-xl p-5 border border-slate-200/80 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.05)] flex items-center justify-between">
            <div>
                <span class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight block">Rs. {{ number_format($totalFine) }}</span>
                <span class="text-xs font-semibold text-slate-500 mt-1 block">Total Fine</span>
            </div>
            <div class="w-12 h-12 rounded-full bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-rose-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
            </div>
        </div>

        <!-- Total Paid -->
        <div class="bg-white rounded-xl p-5 border border-slate-200/80 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.05)] flex items-center justify-between">
            <div>
                <span class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight block">Rs. {{ number_format($totalPaid) }}</span>
                <span class="text-xs font-semibold text-slate-500 mt-1 block">Total Paid</span>
            </div>
            <div class="w-12 h-12 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-emerald-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
            </div>
        </div>

        <!-- Total Due -->
        <div class="bg-white rounded-xl p-5 border border-slate-200/80 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.05)] flex items-center justify-between">
            <div>
                <span class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight block">Rs. {{ number_format($totalDue) }}</span>
                <span class="text-xs font-semibold text-slate-500 mt-1 block">Total Due</span>
            </div>
            <div class="w-12 h-12 rounded-full bg-orange-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-orange-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Fees Table Card -->
    <div class="bg-white rounded-xl border border-slate-200/80 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.05)] p-5 sm:p-6">
        <!-- Card Header Toolbar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-bold text-slate-800">Fees Records &amp; Vouchers</h3>
                <p class="text-xs text-slate-400 mt-0.5">Overview of tuition fees, discounts, and payment history.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <div class="relative">
                    <input 
                        type="text" 
                        placeholder="Search fees..." 
                        class="text-xs rounded-lg border border-slate-200 pl-8 pr-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40"
                    >
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                @if(Route::has('fees.preview'))
                    <a 
                        href="{{ route('fees.preview', ['student' => $student->id, 'month' => now()->month, 'year' => now()->year]) }}" 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-[#0A7EA4] hover:bg-[#086987] rounded-lg shadow-sm transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        <span>Fee Preview</span>
                    </a>
                @endif
            </div>
        </div>

        @include('fees.billing.student-history')

        @if($feeData && isset($feeData['types']))
            <!-- Special Fee Amount / Overrides Section -->
            <div class="mt-8 pt-6 border-t border-slate-100">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="text-xs font-bold text-slate-800">Special Fee Overrides</h4>
                        <p class="text-[11px] text-slate-400">Configure customized fee rates or concessions for this student.</p>
                    </div>
                </div>

                @can('fees.manage')
                    <form method="POST" action="{{ route('fees.overrides.store', $student) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
                        @csrf
                        <div>
                            <label for="override-type" class="block text-[11px] font-bold text-slate-700 mb-1">Fee Type *</label>
                            <select id="override-type" name="fee_type_id" required class="w-full text-xs rounded-lg border border-slate-300 px-3 py-1.5 bg-white text-slate-800">
                                <option value="">Select fee type</option>
                                @foreach($feeData['types'] as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="override-amount" class="block text-[11px] font-bold text-slate-700 mb-1">Special Amount *</label>
                            <div class="flex rounded-lg border border-slate-300 overflow-hidden bg-white">
                                <span class="bg-slate-100 px-2.5 py-1.5 text-xs text-slate-500 border-r border-slate-300">Rs.</span>
                                <input id="override-amount" type="number" name="amount" min="0" max="99999999.99" step="0.01" required placeholder="0.00" class="w-full text-xs px-2.5 py-1.5 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="override-reason" class="block text-[11px] font-bold text-slate-700 mb-1">Reason *</label>
                            <div class="flex gap-2">
                                <input id="override-reason" name="reason" required maxlength="1000" placeholder="e.g. Sibling concession" class="w-full text-xs rounded-lg border border-slate-300 px-2.5 py-1.5 bg-white">
                                <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-white bg-[#0A7EA4] hover:bg-[#086987] rounded-lg shadow-xs shrink-0">Save</button>
                            </div>
                        </div>
                    </form>
                @endcan

                @if(!empty($feeData['overrides']) && $feeData['overrides']->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-slate-500 font-bold bg-slate-50/50">
                                    <th class="py-2.5 px-3">Fee Type</th>
                                    <th class="py-2.5 px-3">Special Amount</th>
                                    <th class="py-2.5 px-3">Reason</th>
                                    <th class="py-2.5 px-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($feeData['overrides'] as $override)
                                    <tr>
                                        <td class="py-2.5 px-3 text-slate-800 font-semibold">{{ $override->feeType?->name ?? 'Archived fee type' }}</td>
                                        <td class="py-2.5 px-3 text-[#0A7EA4] font-bold">Rs. {{ number_format($override->amount, 2) }}</td>
                                        <td class="py-2.5 px-3 text-slate-600">{{ $override->reason }}</td>
                                        <td class="py-2.5 px-3 text-right">
                                            @can('fees.manage')
                                                <form method="POST" action="{{ route('fees.overrides.destroy', [$student, $override->id]) }}" onsubmit="return confirm('Remove this special fee override?');" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-rose-600 hover:text-rose-800 font-semibold text-[11px]">Remove</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
