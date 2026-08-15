@php
    $current = app()->getLocale();
@endphp

<div class="flex items-center overflow-hidden rounded-lg border border-slate-300 text-xs font-semibold">
    <a
        href="{{ route('locale.switch', 'es') }}"
        class="px-2.5 py-1.5 transition {{ $current === 'es' ? 'bg-blue-600 text-white' : 'text-slate-500 hover:bg-slate-50' }}"
    >ES</a>
    <a
        href="{{ route('locale.switch', 'en') }}"
        class="px-2.5 py-1.5 transition {{ $current === 'en' ? 'bg-blue-600 text-white' : 'text-slate-500 hover:bg-slate-50' }}"
    >EN</a>
</div>
