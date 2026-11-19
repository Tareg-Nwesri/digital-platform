import { usePage } from '@inertiajs/vue3';
import type { DriveStep } from 'driver.js';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { trans } from 'laravel-vue-i18n';
import tours from '@/tours/tours.json';

type Popover = NonNullable<DriveStep['popover']>;

type TourStep = {
    element?: string;
    title: string;
    body: string;
    side?: Popover['side'];
    align?: Popover['align'];
};

/**
 * The "?" guide: highlights each part of the current page and explains it.
 * Steps live in tours/tours.json, keyed by the page component name; steps
 * whose element is missing or hidden (a permission, a closed tab, the
 * mobile sidebar) are skipped.
 */
export function usePageTour() {
    const page = usePage();

    const steps = (): TourStep[] =>
        (tours as Record<string, TourStep[]>)[page.component] ?? [];

    const visible = (id: string): boolean => {
        const element = document.querySelector(`[data-tour="${id}"]`);

        if (element === null) {
            return false;
        }

        const box = element.getBoundingClientRect();

        return box.width > 0 && box.height > 0;
    };

    function start(): void {
        const drive: DriveStep[] = steps()
            .filter((step) => !step.element || visible(step.element))
            .map((step) => ({
                element: step.element
                    ? `[data-tour="${step.element}"]`
                    : undefined,
                popover: {
                    title: trans(step.title),
                    description: trans(step.body),
                    side: step.side,
                    align: step.align,
                },
            }));

        if (drive.length === 0) {
            return;
        }

        driver({
            steps: drive,
            showProgress: drive.length > 1,
            animate: true,
            smoothScroll: true,
            allowClose: true,
            overlayOpacity: 0.55,
            stagePadding: 6,
            stageRadius: 10,
            popoverClass: 'app-tour',
            nextBtnText: trans('tour.ui.next'),
            prevBtnText: trans('tour.ui.previous'),
            doneBtnText: trans('tour.ui.done'),
            progressText: trans('tour.ui.progress'),
        }).drive();
    }

    return { start, hasTour: (): boolean => steps().length > 0 };
}
