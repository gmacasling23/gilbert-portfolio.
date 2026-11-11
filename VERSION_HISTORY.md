# OS Theme — Version History

| Version | Notes | Verification |
|---|---|---|
| v1–v5 | Initial portfolio, desktop UI, admin, dynamic content | Historical |
| v6 | MySQL support, transparent taskbar and application icons | Historical |
| v7 | Profile, This PC, contact form | Historical |
| v8 | Local project viewer and manual project folders | Historical |
| v8 Rev. 1 | admin.login taskbar search command | Historical |
| v8 Rev. 2 | Chrome-style project viewer | Historical |
| v8 Rev. 3 | ProjectChrome, tabs and autocomplete | Source baseline |
| **v9** | Professional UI redesign; improved project actions, autocomplete, Profile, Contact, Start menu | Static checks; runtime pending |

Deferred: folder/ZIP import remains disabled.


## os_theme.v9 — Revision 1 (November 9, 2026)
- Replaced the crowded three-column Start menu with a compact taskbar-anchored glass-style menu inspired by the approved mockup.
- Added search, evenly spaced pinned applications, quick access, GM identity and sign-out button.
- Changed sign-in footer label from OS THEME v9.0 to PORTFOLIO v9.0.
- Kept existing app IDs, pinned taskbar behavior, ProjectChrome, admin.login, database and email functionality.
- Validation: PHP/JS syntax and ZIP integrity checked; browser, MySQL and mail runtime tests pending.

## os_theme.v10 — Administrator experience
- Renamed embedded admin window to Administrator.
- Removed OS Theme branding and version labels from admin pages.
- Unauthenticated users see a dedicated full-page sign-in without dashboard navigation.
- Authenticated admin navigation includes Dashboard, Projects, Portfolio, Account, and a one-click POST logout.
- Removed redundant Open in new tab toolbar.
- Logout validates CSRF and redirects straight to sign-in without confirmation.
- Preserved existing project data, database settings, and portfolio features.
- Static PHP and JavaScript checks performed; live MySQL and browser tests pending.

| v10 Rev. 1 | Gilbert Project Library branding, GM logo on ProjectChrome home, compact image/name tiles, local preview paths, sign-in version v10.1 | Static verification only |

| v10 Rev. 2 | Require fresh administrator sign-in whenever the admin window is opened after closing or reloading; preserve authentication during navigation and minimizing | Static verification only |


## v10.2 — Desktop features
- ProjectChrome circular project carousel, up to five visible desktop projects.
- Right-click project properties.
- Personalization (appearance, accent, wallpaper) saved locally.
- About This Portfolio window.
- Global taskbar search for apps, projects and skills.
- Existing admin Account Security page retained for password changes.

Future backlog: Windows notifications, Portfolio Task Manager, ProjectChrome bookmarks/history.

## v10.2 Revision 1 — Personalization UI
- Redesigned Personalization window with wallpaper thumbnail gallery and live desktop preview.
- Added original wallpaper and gradient presets; gallery automatically discovers images in assets/wallpapers/.
- Added appearance choices, accent presets, custom color, Apply Changes, and Restore Defaults.
- Kept existing localStorage key so saved v10.2 preferences are retained.
- No database migration required.
