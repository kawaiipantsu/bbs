# Identity & assets

Style: the wardrive.thugs.red palette on a near-black ground, THUGS red accent,
a CRT monitor motif, mono type, scanline texture.

## Palette

| token | hex | use |
|---|---|---|
| `--bg` | `#0e1013` | page / card ground |
| `--surface` | `#16191f` | panels, monitor body |
| `--surface-2` | `#1d2129` | raised panels |
| `--line` | `#272c36` | borders, hairlines, scanlines |
| `--text` | `#e6e9ef` | primary text |
| `--muted` | `#97a0b0` | secondary text |
| `--accent` / `--bad` | `#e2223b` | THUGS red — the one loud colour |
| `--accent-dim` | `#8f1626` | pressed / danger fill |
| `--ok` | `#35c98b` | success, the prompt caret |
| `--warn` | `#e8a33d` | warnings, pins |
| link | `#9fc4ff` | links inside prose |
| screen phosphor | `#cfe3d6` | the terminal's own default ink |

## Files

### `assets/` — masters (in git)

| file | what |
|---|---|
| `terminal_monitor.png` | 1448×1086 CRT photo — the frame the BBS lives in (provided) |
| `bbs_banner.png` | 1000×750 hero banner used in the README (provided) |
| `favicon.svg` | scalable monitor glyph, red screen + block cursor |
| `logo.svg` | full lockup: monitor mark + `THUGS(red) BBS` wordmark |
| `generate.php` | GD generator — rebuilds everything below |

### `assets/dist/` — generated (gitignored, run `php assets/generate.php`)

| file | size | use |
|---|---|---|
| `favicon-16/32/180/512.png` | — | browser tab / PWA / apple-touch |
| `og-default.png` | 1200×630 | default OpenGraph / Twitter card |
| `github-social-1280x640.png` | 1280×640 | GitHub repo social preview |
| `banner-1500x500.png` | 1500×500 | social header |
| `avatar-default.png` | 256×256 | fallback user avatar |
| `wallpaper-2560x1440.png` | 2560×1440 | desktop wallpaper |

### `html/media/images/` — web copies (served)

`favicon.svg`, `favicon-16/32/180/512.png`, `og-default.png`,
`avatar-default.png`, plus `monitor.png` (the CRT frame) and `knob.png`
(the control-panel knob cut out for the interactive brightness/colour knobs).

Per-entity share cards are rendered on demand by `GET /og/{slug}.png`
(`msg-<id>`, `board-<slug>`, `user-<handle>`, `news-<cat>`, `game-<slug>`),
cached one hour under `storage/cache/og/`.

## Regenerating

```bash
php assets/generate.php      # -> assets/dist/ + copies web-facing ones into html/media/images/
```

Edit the palette constants at the top of `generate.php` (or the SVG masters) and
re-run. No other build tooling.

## Fonts

`html/media/fonts/bbsterm-{regular,bold}.woff2` — a CP437 / box-drawing /
block-element / shade subset of **DejaVu Sans Mono**, ~53 KB each, self-hosted.
License in `html/media/fonts/DejaVu-LICENSE.txt`.
