const clamp = (value) => Math.min(1, Math.max(0, value));
const between = (progress, start, end) =>
    clamp((progress - start) / (end - start));

// Behavioral timings observed in the supplied reference, implemented without its runtime.
export function timelineFrame(progress) {
    const heading = between(progress, 0.08, 0.19);
    const easedHeading =
        heading < 0.5 ? 2 * heading ** 2 : 1 - (-2 * heading + 2) ** 2 / 2;

    return {
        opacity: between(progress, 0.08, 0.16),
        headingX: 10 * (1 - easedHeading),
        copyOpacity: between(progress, 0.08, 0.19),
        copyX: 10 * (1 - between(progress, 0.08, 0.22)),
    };
}

export function timelineProgress(top, height, viewport) {
    if (viewport <= 0) return 0;
    // Normal blocks reveal after their bottom enters the viewport. Tall/zoomed content
    // instead reveals from its top, so it never depends on fitting on screen.
    return height >= viewport
        ? clamp((viewport - top) / viewport)
        : clamp(1 - top / (viewport - height));
}

export function initPublicTimeline(root) {
    if (!root || !('IntersectionObserver' in window)) return;

    const rows = [...root.querySelectorAll('.timeline-step')];
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const direction = document.documentElement.dir === 'rtl' ? -1 : 1;
    const positions = rows.map(() => null);
    let enabled = false;
    let nearby = false;
    let frame = 0;
    let previousTime = 0;

    function render(time) {
        frame = 0;
        const targets = rows.map((row) => {
            const bounds = row.getBoundingClientRect();
            return timelineProgress(
                bounds.top,
                bounds.height,
                window.innerHeight,
            );
        });
        // 80% smoothing at 60Hz; time-normalized for high-refresh screens.
        const blend =
            1 - 0.8 ** (Math.min(time - previousTime || 16.667, 64) / 16.667);
        previousTime = time;
        let settling = false;

        rows.forEach((row, index) => {
            const target = targets[index];
            const current = positions[index] ?? target;
            const next =
                Math.abs(target - current) < 0.0001
                    ? target
                    : current + (target - current) * blend;
            positions[index] = next;
            settling ||= Math.abs(target - next) >= 0.0001;
            const state = timelineFrame(next);
            row.style.setProperty('--timeline-opacity', state.opacity);
            row.style.setProperty(
                '--timeline-heading-x',
                `${direction * state.headingX}px`,
            );
            row.style.setProperty('--timeline-copy-opacity', state.copyOpacity);
            row.style.setProperty(
                '--timeline-copy-x',
                `${direction * state.copyX}px`,
            );
        });

        if (settling) schedule();
    }

    function schedule() {
        if (enabled && nearby && !frame) frame = requestAnimationFrame(render);
    }

    const observer = new IntersectionObserver(
        ([entry]) => {
            nearby = entry.isIntersecting;
            if (nearby) schedule();
            else {
                cancelAnimationFrame(frame);
                frame = 0;
                previousTime = 0;
            }
        },
        { rootMargin: '100px 0px' },
    );

    function configure() {
        enabled = !motion.matches;
        cancelAnimationFrame(frame);
        frame = 0;
        previousTime = 0;
        root.classList.remove('timeline--animated');
        observer.disconnect();
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        window.removeEventListener('pageshow', schedule);

        if (!enabled) return;
        positions.fill(null);
        render(performance.now());
        root.classList.add('timeline--animated');
        observer.observe(root);
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule);
        window.addEventListener('pageshow', schedule);
    }

    motion.addEventListener('change', configure);
    configure();
}
