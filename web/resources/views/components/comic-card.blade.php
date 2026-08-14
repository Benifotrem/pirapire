@props(['image', 'gradient', 'title', 'description'])

<div class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-lg">
    <div class="relative aspect-[4/3] w-full overflow-hidden bg-slate-100">
        {{-- Comic-style illustration. Falls back to a gradient SVG icon if the
             artwork file hasn't been uploaded to public/images/ yet. --}}
        <img
            src="{{ $image }}"
            alt="{{ $title }}"
            loading="lazy"
            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
        >
        <div style="display:none" class="absolute inset-0 flex items-center justify-center bg-gradient-to-br {{ $gradient }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-16 w-16 opacity-90">
                {{ $slot }}
            </svg>
        </div>
    </div>

    <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
        <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
    </div>
</div>
