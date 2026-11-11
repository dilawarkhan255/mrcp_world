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
    /* The top bar stays sticky above the page. Its 1px bottom border and the one under the logo
       form a single line (both bars are 5rem), so no extra strip is drawn below it. */
    .fi-topbar { z-index: 30; }
    /* Only the menu scrolls; the logo row stays above it, and the scrollbar is thin. */
    .fi-sidebar-header { position: relative; z-index: 10; flex-shrink: 0; }
    .fi-sidebar-nav { overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: #dde4ef transparent; }
    .dark .fi-sidebar-nav { scrollbar-color: #26304a transparent; }
    .fi-sidebar-nav::-webkit-scrollbar { width: 6px; }
    .fi-sidebar-nav::-webkit-scrollbar-thumb { border-radius: 9999px; background: #dde4ef; }
    .dark .fi-sidebar-nav::-webkit-scrollbar-thumb { background: #26304a; }
    .fi-sidebar-nav::-webkit-scrollbar-track { background: transparent; }
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
    .fi-sidebar-item-active .fi-sidebar-item-label { font-weight: 600; }
    .mw-topbar-btn:hover { background: rgba(11, 42, 111, 0.08); color: #0B2A6F; }
    .dark .mw-topbar-btn:hover { background: rgba(27, 184, 214, 0.12); color: #1BB8D6; }
    /* Filament's dark primary text uses the lighter shade, which is too dark for navy: use the logo's cyan. */
    /* The sidebar's own collapse button is replaced by the top bar toggle on desktop. */
    .fi-sidebar-header .fi-icon-btn.ms-auto { display: none !important; }

    /* ---- The logo's colour mix, as in the student app (resources/css/app.css). ---- */
    :root { --logo-gradient: linear-gradient(90deg, #f28c28 0%, #f5c518 22%, #2dbe4e 45%, #1bb8d6 68%, #1e6fd9 85%, #5b3fc4 100%);
            --logo-gradient-strong: linear-gradient(115deg, #c2410c 0%, #a16207 20%, #15803d 42%, #0e7490 64%, #1d4ed8 84%, #6d28d9 100%); }
    /* Icons stroked (outline icons) or filled (solid icons) with the logo gradient (partials/logo-gradient-defs). */
    .fi-sidebar-item:not(.fi-sidebar-item-active) .fi-sidebar-item-icon,
    .mw-yajra td.mw-yajra-actions .fi-icon-btn svg[stroke="currentColor"] { stroke: url(#mw-logo-stroke); }
    .mw-yajra td.mw-yajra-actions .fi-icon-btn svg[fill="currentColor"] { fill: url(#mw-logo-stroke); }
    .dark .fi-sidebar-item:not(.fi-sidebar-item-active) .fi-sidebar-item-icon,
    .dark .mw-yajra td.mw-yajra-actions .fi-icon-btn svg[stroke="currentColor"] { stroke: url(#mw-logo-stroke-dark); }
    .dark .mw-yajra td.mw-yajra-actions .fi-icon-btn svg[fill="currentColor"] { fill: url(#mw-logo-stroke-dark); }
    .mw-yajra td.mw-yajra-actions .fi-icon-btn:hover { background: rgba(30, 111, 217, 0.08); }
    /* The active sidebar item and the primary buttons: the deeper logo gradient with white text. */
    .fi-sidebar-item-active > a, .fi-sidebar-item-active > button,
    .dark .fi-sidebar-item-active > a, .dark .fi-sidebar-item-active > button,
    .fi-btn.fi-btn-color-primary:not(.fi-btn-outlined) {
        background-image: var(--logo-gradient-strong) !important; background-size: 140% 100%; background-position: 0 0;
        transition: background-position 0.3s ease, filter 0.2s ease; }
    .fi-sidebar-item-active > a:hover, .fi-btn.fi-btn-color-primary:not(.fi-btn-outlined):hover { background-position: 100% 0; filter: brightness(1.05); }
    .fi-sidebar-item-active .fi-sidebar-item-label, .fi-sidebar-item-active .fi-sidebar-item-icon,
    .dark .fi-sidebar-item-active .fi-sidebar-item-label, .dark .fi-sidebar-item-active .fi-sidebar-item-icon,
    .fi-btn.fi-btn-color-primary:not(.fi-btn-outlined), .fi-btn.fi-btn-color-primary:not(.fi-btn-outlined) * { color: #fff !important; }
    /* Toggles that are on, primary links and the current page of a table: the logo mix too. */
    button[role="switch"][aria-checked="true"] { background-image: var(--logo-gradient-strong) !important; }
    .fi-link.fi-color-primary > span { background-image: var(--logo-gradient-strong); -webkit-background-clip: text; background-clip: text; color: transparent; -webkit-text-fill-color: transparent; }
    .dark .fi-link.fi-color-primary > span { background-image: linear-gradient(115deg, #f5a24a, #f5c518, #4ade80, #22d3ee, #60a5fa, #a78bfa); }
    .fi-link.fi-color-primary svg[stroke="currentColor"] { stroke: url(#mw-logo-stroke); }
    .fi-link.fi-color-primary svg[fill="currentColor"] { fill: url(#mw-logo-stroke); }
    .mw-yajra .dt-paging .dt-paging-button.current { background-image: var(--logo-gradient-strong) !important; color: #fff !important; border-color: transparent !important; }

    /* Stat cards of the dashboard: the logo line on top, like the student cards. */
    .fi-wi-stats-overview-stat { position: relative; overflow: hidden; }
    .fi-wi-stats-overview-stat::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px; background: var(--logo-gradient); }
    /* Dashboard widgets: the logo line on top of every card, rainbow section and empty-state icons. */
    .fi-wi-widget .fi-section, .fi-wi-table .fi-ta-ctn { position: relative; overflow: hidden; }
    .fi-wi-widget .fi-section::before, .fi-wi-table .fi-ta-ctn::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px; background: var(--logo-gradient); z-index: 1; }
    .fi-section-header-icon, .fi-ta-empty-state-icon { stroke: url(#mw-logo-stroke); }
    .dark .fi-section-header-icon, .dark .fi-ta-empty-state-icon { stroke: url(#mw-logo-stroke-dark); }
    .fi-ta-empty-state-icon-ctn { background: linear-gradient(135deg, rgba(242, 140, 40, 0.16), rgba(45, 190, 78, 0.14), rgba(27, 184, 214, 0.16), rgba(91, 63, 196, 0.16)) !important; }
    /* Publishing workflow: gradient badges, icons and borders. */
    .mw-workflow__step { border: 1px solid transparent !important; background: linear-gradient(#fff, #fff) padding-box, var(--logo-gradient) border-box !important; }
    .dark .mw-workflow__step { background: linear-gradient(rgb(var(--gray-900)), rgb(var(--gray-900))) padding-box, var(--logo-gradient) border-box !important; }
    .mw-workflow__badge { background: var(--logo-gradient-strong) !important; }
    .mw-workflow__icon, .dark .mw-workflow__icon { stroke: url(#mw-logo-stroke); }
    .dark .mw-workflow__icon { stroke: url(#mw-logo-stroke-dark); }

    /* Table thumbnails (past paper covers, atlas images): the whole picture fits, on a soft tile. */
    .mw-thumb { object-fit: contain !important; border-radius: 0.5rem; padding: 3px; background: rgba(148, 163, 184, 0.12); border: 1px solid rgba(148, 163, 184, 0.25); }
    .mw-yajra .fi-ta-image img { max-width: 56px; max-height: 40px; }
    .fi-fo-file-upload .filepond--root { max-width: 32rem; }

    /* Create / Edit / View windows (App\Filament\Support\CrudModal): a centred card with the logo line on top,
       a tinted icon, a header and a button bar that stay put while the form scrolls. */
    /* No overflow: hidden here: it would stop the sticky header and footer from sticking. */
    .fi-modal-window { border-radius: 1rem !important; box-shadow: 0 25px 60px -20px rgba(11, 18, 36, 0.45) !important; }
    .fi-modal-window::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: var(--logo-gradient); border-radius: 1rem 1rem 0 0; z-index: 30; }
    .fi-modal-window .fi-modal-header.fi-sticky { border-radius: 1rem 1rem 0 0; }
    .fi-modal-window .fi-modal-footer.fi-sticky { border-radius: 0 0 1rem 1rem; }
    .fi-modal-window .fi-modal-header.fi-sticky { padding-top: 1.5rem; background: linear-gradient(180deg, rgba(27, 184, 214, 0.06), transparent 70%), #fff; }
    .dark .fi-modal-window .fi-modal-header.fi-sticky { background: linear-gradient(180deg, rgba(27, 184, 214, 0.08), transparent 70%), rgb(var(--gray-900)); }
    .fi-modal-window .fi-modal-heading { font-family: 'Sora', 'Manrope', sans-serif; font-size: 1.15rem; letter-spacing: -0.01em; }
    .fi-modal-header .fi-modal-icon { stroke: url(#mw-logo-stroke); }
    .dark .fi-modal-header .fi-modal-icon { stroke: url(#mw-logo-stroke-dark); }
    .fi-modal-header .fi-modal-icon { color: inherit; }
    .fi-modal-header :has(> .fi-modal-icon) { background: linear-gradient(135deg, rgba(242, 140, 40, 0.16), rgba(45, 190, 78, 0.14), rgba(27, 184, 214, 0.16), rgba(91, 63, 196, 0.16)) !important; }
    .fi-modal-window .fi-modal-content { padding-top: 1.25rem; padding-bottom: 1.25rem; }
    .fi-modal-window .fi-modal-footer.fi-sticky { background: rgba(248, 250, 252, 0.96); backdrop-filter: blur(6px); }
    .dark .fi-modal-window .fi-modal-footer.fi-sticky { background: rgba(14, 23, 48, 0.96); }
    .fi-modal-close-btn { border-radius: 0.5rem; }
    .fi-modal-close-btn:hover { background: rgba(30, 111, 217, 0.08); }
    /* Form sections inside a modal: soft cards. */
    .fi-modal-window .fi-section { box-shadow: none; border: 1px solid rgba(148, 163, 184, 0.25); }

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
