@if (($ledDisplay['enabled'] ?? false) && ! empty($ledDisplay['ads']))
    <a
        id="led-display"
        href="#"
        target="_blank"
        rel="noopener sponsored"
        class="mx-3 hidden h-[80%] flex-1 items-center overflow-hidden rounded-md border border-black bg-black px-4 sm:flex"
        style="box-shadow: inset 0 0 10px rgba(0,0,0,0.85);"
        data-mode="{{ $ledDisplay['color'] }}"
        data-ads="{{ json_encode($ledDisplay['ads']) }}"
    >
        <span id="led-display-text" class="whitespace-nowrap font-mono text-3xl font-bold tracking-wider"></span>
    </a>

    <script>
    (function () {
        const el = document.getElementById('led-display');
        if (!el) return;

        const colors = {
            red: ['#ff1a1a', '0 0 6px #ff1a1a, 0 0 12px #ff0000'],
            green: ['#22ff55', '0 0 6px #22ff55, 0 0 12px #00ff00'],
            blue: ['#33d1ff', '0 0 6px #33d1ff, 0 0 12px #00aaff'],
        };
        const mode = el.dataset.mode;
        const ads = JSON.parse(el.dataset.ads || '[]');
        const textEl = document.getElementById('led-display-text');
        let i = 0;

        function pickColor() {
            if (mode === 'mixed') {
                const keys = Object.keys(colors);
                return colors[keys[Math.floor(Math.random() * keys.length)]];
            }
            return colors[mode] || colors.red;
        }

        function showNext() {
            if (!ads.length) return;
            const ad = ads[i % ads.length];
            i++;

            el.href = ad.url;
            const [color, glow] = pickColor();
            textEl.style.color = color;
            textEl.style.textShadow = glow;
            textEl.textContent = ad.message;

            // Scroll speed proportional to message length so longer messages
            // don't fly past unreadably fast.
            const duration = Math.max(6, ad.message.length * 0.22);
            textEl.style.animation = 'none';
            void textEl.offsetWidth; // restart the CSS animation
            textEl.style.animation = `led-scroll ${duration}s linear`;

            setTimeout(showNext, duration * 1000);
        }

        showNext();
    })();
    </script>
@endif
