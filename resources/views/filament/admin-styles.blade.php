{{-- Brand polish for the admin panel. Filament ships precompiled CSS, so the
     dashboard uses these small hand-written classes instead of Tailwind. --}}
<style>
    /* The sidebar header and the top bar share one height, so their bottom borders line up. */
    .fi-sidebar-header, .fi-topbar > nav { height: 5rem; min-height: 5rem; }
    .fi-sidebar-header { justify-content: center; }
    /* The same colours as the student app (resources/css/app.css). */
    .fi-body { background-color: #f3f6fb; }
    .dark .fi-body { background-color: #0b1224; }
    .dark .fi-sidebar, .dark .fi-sidebar-header, .dark .fi-topbar > nav { background-color: #0e1730; }
    .fi-sidebar-item-active > a, .fi-sidebar-item-active > button { background-color: #e8edf8; }
    .dark .fi-sidebar-item-active > a, .dark .fi-sidebar-item-active > button { background-color: #123047; }
    /* Thin rainbow line from the logo under the top bar. */
    /* The top bar stays sticky above the page (it is already positioned, so the line below needs no override). */
    .fi-topbar { z-index: 30; }
    .fi-topbar::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: linear-gradient(90deg, #f28c28 0%, #f5c518 22%, #2dbe4e 45%, #1bb8d6 68%, #1e6fd9 85%, #5b3fc4 100%); opacity: 0.85; }
    .fi-sidebar-group-label { letter-spacing: 0.06em; text-transform: uppercase; font-size: 0.7rem; }
    .fi-header-heading { font-family: 'Sora', 'Manrope', sans-serif; letter-spacing: -0.01em; }
    .fi-wi-stats-overview-stat { transition: box-shadow 150ms ease, transform 150ms ease; }
    a.fi-wi-stats-overview-stat:hover { box-shadow: 0 8px 24px -12px rgba(11, 42, 111, 0.45); transform: translateY(-1px); }
    .fi-section { border-radius: 0.875rem; }

    /* Top bar buttons: sidebar toggle and Day/Dark, the same size as Filament's bell. */
    .mw-topbar-btn { align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; border: 1px solid rgba(148, 163, 184, 0.35); color: rgb(100 116 139); transition: background 150ms ease, color 150ms ease, border-color 150ms ease; }
    .mw-topbar-btn:hover { border-color: rgba(11, 42, 111, 0.4); }
    .dark .mw-topbar-btn { border-color: rgba(148, 163, 184, 0.2); }
    .mw-topbar-btn.mw-appearance, .mw-topbar-btn.mw-topbar-toggle { display: inline-flex; }
    /* One menu button for every screen size (ours), like the student app. */
    .fi-topbar-open-sidebar-btn, .fi-topbar-close-sidebar-btn { display: none !important; }

    /* ---- The same look as the student app: flat bars with a 1px border, no shadows. ---- */
    .fi-topbar > nav { box-shadow: none !important; --tw-ring-shadow: 0 0 #0000 !important; border-bottom: 1px solid #dde4ef; background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(8px); }
    .dark .fi-topbar > nav { border-bottom-color: #26304a; background: rgba(14, 23, 48, 0.92); }
    .fi-sidebar-header { box-shadow: none !important; --tw-ring-shadow: 0 0 #0000 !important; border-bottom: 1px solid #dde4ef; background: #fff; }
    .dark .fi-sidebar-header { border-bottom-color: #26304a; background: #0e1730; }
    .fi-sidebar { border-right: 1px solid #dde4ef; background: #fff; }
    .dark .fi-sidebar { border-right-color: #26304a; background: #0e1730; }
    .fi-sidebar-nav { padding: 0.75rem 0.5rem !important; row-gap: 1rem !important; }
    .fi-sidebar-item a, .fi-sidebar-item button { border-radius: 0.5rem; padding-top: 0.5rem; padding-bottom: 0.5rem; }
    .fi-sidebar-item-label { font-size: 0.875rem; font-weight: 500; }
    .fi-sidebar-item a:hover, .fi-sidebar-item button:hover { background-color: #eef2fa; }
    .dark .fi-sidebar-item a:hover, .dark .fi-sidebar-item button:hover { background-color: #172241; }
    .fi-sidebar-item-active .fi-sidebar-item-label, .fi-sidebar-item-active .fi-sidebar-item-icon { color: #0B2A6F !important; font-weight: 600; }
    .mw-topbar-btn:hover { background: rgba(11, 42, 111, 0.08); color: #0B2A6F; }
    .dark .mw-topbar-btn:hover { background: rgba(27, 184, 214, 0.12); color: #1BB8D6; }
    /* Filament's dark primary text uses the lighter shade, which is too dark for navy: use the logo's cyan. */
    .dark .fi-sidebar-item-active .fi-sidebar-item-label, .dark .fi-sidebar-item-active .fi-sidebar-item-icon { color: #1BB8D6 !important; }
    /* The sidebar's own collapse button is replaced by the top bar toggle on desktop. */
    .fi-sidebar-header .fi-icon-btn.ms-auto { display: none !important; }

    .mw-workflow { display: grid; gap: 1rem; grid-template-columns: repeat(1, minmax(0, 1fr)); counter-reset: none; }
    @media (min-width: 768px) { .mw-workflow { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1280px) { .mw-workflow { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .mw-workflow__step { display: flex; flex-direction: column; gap: 0.5rem; padding: 1rem; border-radius: 0.75rem; border: 1px solid rgba(11, 42, 111, 0.18); background: rgba(11, 42, 111, 0.04); }
    .mw-workflow__head { display: flex; align-items: center; justify-content: space-between; }
    .mw-workflow__badge { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 9999px; background: #0B2A6F; color: #fff; font-weight: 700; font-size: 0.8rem; }
    .mw-workflow__icon { width: 1.25rem; height: 1.25rem; color: #0B2A6F; }
    .mw-workflow__title { font-weight: 700; font-size: 0.95rem; }
    .mw-workflow__body { flex: 1; font-size: 0.85rem; line-height: 1.45; opacity: 0.8; }
    .dark .mw-workflow__step { border-color: rgba(27, 184, 214, 0.22); background: rgba(27, 184, 214, 0.06); }
    .dark .mw-workflow__icon { color: #1BB8D6; }
</style>
