{{-- Brand polish for the admin panel. Filament ships precompiled CSS, so the
     dashboard uses these small hand-written classes instead of Tailwind. --}}
<style>
    .fi-sidebar-header { height: 5rem; }
    .fi-sidebar-group-label { letter-spacing: 0.06em; text-transform: uppercase; font-size: 0.7rem; }
    .fi-header-heading { font-family: 'Sora', 'Manrope', sans-serif; letter-spacing: -0.01em; }
    .fi-wi-stats-overview-stat { transition: box-shadow 150ms ease, transform 150ms ease; }
    a.fi-wi-stats-overview-stat:hover { box-shadow: 0 8px 24px -12px rgba(11, 110, 120, 0.45); transform: translateY(-1px); }
    .fi-section { border-radius: 0.875rem; }

    .mw-appearance { display: grid; gap: 0.375rem; padding: 0.75rem 1rem; border-top: 1px solid rgba(148, 163, 184, 0.25); }
    .mw-appearance__label { font-size: 0.65rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; opacity: 0.6; }
    .mw-appearance__group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.25rem; padding: 0.25rem; border-radius: 0.6rem; background: rgba(148, 163, 184, 0.15); }
    .mw-appearance__group--stacked { grid-template-columns: 1fr; }
    .mw-appearance__btn { display: flex; align-items: center; justify-content: center; gap: 0.375rem; padding: 0.375rem 0.5rem; border-radius: 0.45rem; font-size: 0.75rem; font-weight: 600; transition: background 150ms ease, color 150ms ease; }
    .mw-appearance__btn:hover { background: rgba(148, 163, 184, 0.2); }
    .mw-appearance__btn--active, .mw-appearance__btn--active:hover { background: #0B6E78; color: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15); }
    .dark .mw-appearance__btn--active { background: #5EC8D2; color: #0f172a; }

    .mw-workflow { display: grid; gap: 1rem; grid-template-columns: repeat(1, minmax(0, 1fr)); counter-reset: none; }
    @media (min-width: 768px) { .mw-workflow { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1280px) { .mw-workflow { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .mw-workflow__step { display: flex; flex-direction: column; gap: 0.5rem; padding: 1rem; border-radius: 0.75rem; border: 1px solid rgba(11, 110, 120, 0.18); background: rgba(11, 110, 120, 0.04); }
    .mw-workflow__head { display: flex; align-items: center; justify-content: space-between; }
    .mw-workflow__badge { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 9999px; background: #0B6E78; color: #fff; font-weight: 700; font-size: 0.8rem; }
    .mw-workflow__icon { width: 1.25rem; height: 1.25rem; color: #0B6E78; }
    .mw-workflow__title { font-weight: 700; font-size: 0.95rem; }
    .mw-workflow__body { flex: 1; font-size: 0.85rem; line-height: 1.45; opacity: 0.8; }
    .dark .mw-workflow__step { border-color: rgba(94, 200, 210, 0.2); background: rgba(94, 200, 210, 0.05); }
    .dark .mw-workflow__icon { color: #5EC8D2; }
</style>
