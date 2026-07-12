# Handoff: Gruppen-Detailseite Redesign (Option 3b)

## Overview
Redesign der Gruppen-Detailseite (`/groups/{name}`) im MHN-Mitgliederverzeichnis — die Seite, auf der Mitglieder und Gruppenverwalter*innen verwaltet werden. Passend zum bereits gewählten Listendesign (Option 2a).

## About the Design Files
Die Dateien in diesem Bundle sind **Design-Referenzen in HTML** (aus einem Prototyping-Tool), keine produktionsfertige Vorlage zum 1:1-Kopieren. Aufgabe: dieses Design im bestehenden Stack nachbauen — als **Nette-Latte-Template**, analog zur Struktur von `original-group-detail.html` (mitgeliefert als Referenz für Felder, Aktionen und JS-Hooks). Übernimm den bestehenden Rahmen (Topbar/Sidebar, `style.css`, Tabler Icons) sowie die JS-Helfer `groupAction()`, `submitForm()`, `addMember()`, `addOwner()` unverändert.

`3b-snippet.html` ist der reine Ausschnitt der Option „3b" aus dem Mockup — Layout- und Stilreferenz für den Seiteninhalt (Header + zwei Panels), keine lauffähige Seite für sich.

## Fidelity
**High-fidelity** für Layout, Abstände, Typografie und Interaktionslogik der zwei Listen-Panels. **Farben sind ein Vorschlag** (kein CSS aus dem echten Projekt wurde bereitgestellt) — gegen die tatsächlichen CSS-Variablen abgleichen (`--panel-bg`, `--border-color`, `--border-radius`, `--accent`, `--accent-light`, `--color-muted`).

**Nicht im Scope dieses Mockups:** der „Einstellungen"-Dialog (Formular mit Beitrittspolitik, Sichtbarkeit, Mailingliste etc.) — dieser bleibt unverändert wie in `original-group-detail.html`, es sei denn, ein separater Auftrag dafür folgt.

## Screen: Gruppen-Detailseite (Option 3b)

### Layout
- Container: `max-width` ~700px, Innenabstand 26px, Hintergrund `#f6f7f9`, `border-radius:10px`, Schatten `0 1px 3px rgba(20,28,45,.08), 0 10px 30px rgba(20,28,45,.06)`.
- Header-Zeile: `display:flex; justify-content:space-between; align-items:flex-start`.
  - Links: Breadcrumb „Gruppen / Teams" (12px, `#9aa0ad`, „Gruppen" als Link), darunter `<h1>` Gruppenname (22px/700, `#1c2433`), darunter Beschreibungstext (13px, `#8a90a0`, max-width 460px, line-height 1.5).
  - Rechts (`flex:none`, `gap:8px`): zwei Buttons nebeneinander — „Verlassen" (Outline, neutral) und „Einstellungen" (**gefüllt/primär** in 3b: `background:#2f5fa6; color:#fff`, um die wichtigste Verwaltungsaktion hervorzuheben).
- Inhalt: zwei gleich breite Panels nebeneinander (`display:grid; grid-template-columns:1fr 1fr; gap:16px`) — links „Mitglieder", rechts „Gruppenverwalter*innen".

### Panel (beide identisch aufgebaut)
- `background:#fff; border:1px solid #e6e8ec; border-radius:9px; padding:16px`.
- Kopf: Icon (`ti-users` bzw. `ti-crown`, `#9aa0ad`) + Titel (12.5px/600, `#1c2433`) + Anzahl-Pill (11px, `#aab0bb` auf `#f1f3f6`, `border-radius:99px`).
- Liste: pro Person eine Zeile (`display:flex; align-items:center; gap:8px; padding:7px 0; border-top:1px solid #f0f2f5` — erste Zeile bekommt die Trennlinie auch, wirkt wie Abschluss des Headers).
  - Name als Link (13px/500, `#1c2433`, `text-decoration:none`, `text-overflow:ellipsis` bei Überlänge).
  - Entfernen-Button rechts: reines Icon (`ti-x`), `24×24px`, `border:none; background:none; color:#c2c7d0`, kein Hover-Text nötig — `aria-label="Mitglied entfernen"` bzw. `"Als Gruppenverwalter*in entfernen"` beibehalten.
- Footer (Hinzufügen): `display:flex; gap:6px; margin-top:12px; padding-top:12px; border-top:1px solid #f0f2f5` — Text-Input (mit `<datalist>` wie im Original, `placeholder="Hinzufügen…"`, `border:1px solid #e0e3e8; border-radius:6px; padding:6px 9px; font-size:12.5px`) + quadratischer Icon-Button (`ti-plus`, `28×28px`, Outline).

### Buttons (Header)
- „Verlassen": Outline, `padding:7px 12px; border-radius:7px; border:1px solid #e0e3e8; background:#fff; color:#5a6273; font-size:12.5px; font-weight:500`.
- „Einstellungen": gefüllt, `padding:7px 12px; border-radius:7px; background:#2f5fa6; border:1px solid #2f5fa6; color:#fff; font-size:12.5px; font-weight:600`.

### Typography
Icons: Tabler Icons (`ti-users-group`, `ti-users`, `ti-crown`, `ti-x`, `ti-plus`, `ti-user-minus`, `ti-settings`) — identisch zu den bereits im Projekt verwendeten Klassen. Schriftgrößen/-gewichte wie oben angegeben übernehmen; Schriftfamilie an die bestehende `style.css` anpassen.

## Interactions & Behavior
Unverändert aus `original-group-detail.html`:
- „Verlassen" → `groupAction('/groups/{name}/leave', 'POST')`.
- „Einstellungen" → öffnet bestehenden Dialog `#group-edit-dialog` (Inhalt unverändert, s. o.).
- Mitglied entfernen → `groupAction('/groups/{name}/members/{username}', 'DELETE')`.
- Owner entfernen → `groupAction('/groups/{name}/owners/{username}', 'DELETE')`.
- Mitglied hinzufügen → Formular-Submit ruft `addMember(event, this)` auf, das intern `groupAction('/groups/{name}/members/{username}', 'POST')` aufruft. Datalist-Vorschläge (Nicht-Mitglieder) bleiben wie im Original.
- Owner hinzufügen → analog `addOwner(event, this)` → `POST /groups/{name}/owners/{username}`.
- Owner-Badge bei Namen entfällt in 3b, da Owner in einer eigenen Spalte stehen — kein Mitglied erscheint doppelt markiert.

## State Management
Keine neuen Client-State-Anforderungen. Daten (Mitgliederliste, Owner-Liste, Nicht-Mitglieder für Datalist) kommen weiterhin server-seitig, analog zu `original-group-detail.html`.

## Design Tokens (Vorschlag, gegen echte Variablen abgleichen)
- Flächenfarben: Panel-Hintergrund `#f6f7f9`, Karten `#fff`, Rahmen `#e6e8ec` / `#e0e3e8` / `#f0f2f5`.
- Text: Primär `#1c2433`, sekundär `#5a6273`, gedämpft `#8a90a0`/`#9aa0ad`/`#aab0bb`/`#c2c7d0`.
- Primärfarbe (Einstellungen-Button): `#2f5fa6`.
- Radien: Panel 10px, Karten 9px, Buttons 6–7px, Pills 99px.
- Abstände: Panel-Innenabstand 16px, Zeilenabstand 7px, Spaltenabstand 16px.

## Assets
Tabler Icons (bereits im Projekt via `/css/tabler-icons.min.css`): `ti-users-group`, `ti-users`, `ti-crown`, `ti-x`, `ti-plus`, `ti-user-minus`, `ti-settings`.

## Files
- `3b-snippet.html` — HTML/Inline-Style-Ausschnitt der Option 3b (Layout- und Stilreferenz).
- `original-group-detail.html` — das bestehende, echte Seiten-Markup (rekonstruiert aus dem gerenderten HTML) zur Orientierung bei Feldern, IDs, Aktionen (`groupAction`, `submitForm`, `addMember`, `addOwner`) und dem unveränderten Einstellungen-Dialog.
