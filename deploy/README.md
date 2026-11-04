# Desplegament per sessions

Aquesta carpeta conté un prompt per a cada sessió de Claude Code necessària per construir
**tota** la plantilla que descriu `prompt_new.md`. Cada sessió fusiona a `main` la feina de les
anteriors, fa una part, deixa un informe amb captures a `deploy-status/`, una pull request i
`docs/progress.md` al dia, de manera que la següent sessió sap on continuar només amb el que hi ha
al repositori.

## Preparació (una sola vegada)

1. **Repositori nou i buit** a GitHub (p. ex. `SIENSIS/ci4app`), amb GitHub Actions activat.
2. Copia-hi des d'aquest repositori **`prompt_new.md`**, **`claude_suggest.md`** i **tota la carpeta
   `deploy/`**, i fes-ne el primer commit a `main`.
3. Obre una sessió de Claude Code sobre el repositori nou i escriu:

   ```
   executa deploy/00-bootstrap.md
   ```

   La sessió 00 s'encarrega de tot: comprova l'entorn (PHP, extensions, Composer, xarxa) i
   instal·la el que falti si pot; crea el projecte CodeIgniter 4.7; instal·la Shield, Settings,
   **kpcrudy** i **kpapy** (`^1.0`) i les eines (PHPUnit, PHPStan, php-cs-fixer); munta la CI de
   GitHub Actions; converteix els dos fitxers en `docs/specification.md` i `CLAUDE.md`; escriu el
   pla, el registre de progrés i el primer informe a `deploy-status/`; i obre una pull request.
   Si l'entorn no té el que cal i no ho pot instal·lar, s'atura i et diu exactament què configurar.
4. **Entorn.** Claude Code pot treballar de dues maneres, i la sessió 00 detecta quina fas servir:
   - **Al navegador (claude.ai/code):** cada sessió és una màquina al núvol que comença de zero.
     La sessió 00 instal·la el que falti i genera **`deploy/setup.sh`**; enganxa'l com a *Setup
     script* de l'entorn (menú de l'entorn a la barra de títol de la sessió → *Edit* → *Setup
     script*), i les sessions següents començaran amb tot instal·lat. L'entorn ha de tenir accés de
     xarxa a Packagist i GitHub.
   - **A VS Code amb l'extensió de Claude (en local, amb XAMPP):** Claude fa servir el PHP de XAMPP
     del teu ordinador i **no cal cap script**. La sessió 00 comprova la versió de PHP (8.2 o
     superior) i les extensions, i, sense tocar res sense preguntar, et diu què has d'activar al
     `php.ini` de XAMPP (`intl`, `zip`, `gd`, `sodium`, `sqlite3`…), si cal instal·lar Composer o
     Node.js (per a les captures), i et proposa la base de dades a MariaDB de XAMPP. Ho deixa escrit
     a **`deploy/setup-local.md`** per si canvies d'ordinador. L'aplicació se serveix amb
     `php spark serve` (o amb un *virtual host* d'Apache que apunti a `public/`).
   - Pots alternar entre tots dos llocs: el repositori de GitHub és el mateix, i cada sessió
     comença fent `pull` de `main`.

## Com continuar

A cada **sessió nova** de Claude Code:

```
executa deploy/next.md
```

Busca quina és la sessió següent i l'executa. També pots executar-ne una de concreta
(`executa deploy/07-rbac.md`).

**Cada sessió, en començar, unifica les branques a `main`:** fusiona (`merge --no-ff`) a `main`,
per ordre, les branques de les sessions anteriors que estiguin acabades i encara no hi siguin;
passa les proves després de cada fusió, marca la sessió com a *merged* a `docs/progress.md`, fa
`push` de `main` i només llavors crea la seva branca. Si una branca no està acabada, si hi ha un
conflicte en el codi o si les proves fallen, s'atura i t'ho diu. Si vols revisar una fase abans
que es fusioni, fes-ho abans d'executar la sessió següent (la pull request de cada sessió queda a
GitHub i es marca com a fusionada sola).

> Perquè les sessions puguin fer `push` a `main`, la branca no ha d'estar protegida contra els
> pushes de Claude. Si ho està, la sessió s'aturarà i et demanarà que facis el merge de la pull
> request a GitHub.

**Cada sessió, en acabar, deixa un informe a `deploy-status/NN-<nom>/`:**

- `README.md`: què s'ha fet, com s'ha fet (disseny, classes i fitxers principals, taules, rutes,
  permisos, comandes), decisions i desviacions de l'especificació, com provar-ho (ordres, URLs i
  usuaris de demo), resultats de les proves, problemes coneguts i notes per a la sessió següent;
- `screenshots/`: captures (Playwright, amb dades de demo) de les pantalles noves, també en mòbil
  i en mode fosc quan escau, cadascuna amb el seu peu al README. Si la fase no té pantalles (p. ex.
  una comanda), hi posa la sortida de la consola.

La sessió 00 crea `deploy-status/README.md` (el format) i `deploy-status/screenshot.mjs` (l'script
de captures que reutilitzen totes les sessions).

**Altres casos:**

- **Una sessió s'ha aturat a mitges** (context ple, límit de temps): `executa deploy/next.md` la
  detecta i la continua, o bé `executa deploy/continue.md 07`.
- **Vols canvis en una fase:** `executa deploy/review-fixes.md 07` i els teus comentaris (o deixa'ls
  com a comentaris de revisió a la pull request).

## Sessions

| # | Fitxer | Contingut | Fita |
|---|---|---|---|
| 00 | `00-bootstrap.md` | Especificació, `CLAUDE.md`, llibreries, CI, pla | |
| 01 | `01-core-modules.md` | Mòduls, manifest, `modules:sync`, `make:module` | |
| 02 | `02-core-registry.md` | Catàleg de permisos, settings, activar/desactivar, dades de demo | |
| 03 | `03-themes.md` | Temes i vistes estàndard | |
| 04 | `04-frontend.md` | Assets, JavaScript, CSP, mode fosc, responsive, PWA | |
| 05 | `05-auth.md` | Autenticació i sessions | |
| 06 | `06-auth-2fa.md` | 2FA i reautenticació | |
| 07 | `07-rbac.md` | Permisos centralitzats | |
| 08 | `08-menus-security.md` | Menús del back office i seguretat base | |
| 09 | `09-kpcrudy.md` | Integració de kpcrudy | |
| 10 | `10-kpapy.md` | Integració de kpapy i autenticació de l'API | |
| 11 | `11-notifications-scheduler.md` | Notificacions i tasques programades | |
| 12 | `12-activity.md` | Registre d'activitat | |
| 13 | `13-storage.md` | Emmagatzematge i pujades | |
| 14 | `14-settings.md` | Settings i Settings › Seguretat | |
| 15 | `15-users.md` | Usuaris, grups i matriu de permisos | |
| 16 | `16-dashboard.md` | Tauler i ginys | |
| 17 | `17-news.md` | Mòdul News (de referència) | |
| 18 | `18-installer.md` | Instal·lador i CLI | |
| 19 | `19-mvp-review.md` | **Revisió del MVP** | **Fita 1: MVP** |
| 20 | `20-pages.md` | Pages | |
| 21 | `21-menus.md` | Editor de menús | |
| 22 | `22-files-manager.md` | Gestor de fitxers i ACL | |
| 23 | `23-files-picker.md` | Selector de fitxers i avatars | |
| 24 | `24-events-core.md` | Calendari i audiències | |
| 25 | `25-events-ical-api.md` | iCal i API de calendari | |
| 26 | `26-core-api.md` | API del nucli completa | |
| 27 | `27-backup.md` | Còpies de seguretat | |
| 28 | `28-updates.md` | Actualitzacions | |
| 29 | `29-module-packages.md` | Pantalla de mòduls i instal·lació per ZIP | |
| 30 | `30-security-check.md` | `security:check` i estat de seguretat | **Fita 2: funcionalitat completa** |
| 31 | `31-i18n.md` | Repàs d'idiomes ca/es/en | |
| 32 | `32-tasks-module.md` | Mòdul `Tasks` i eines per a mòduls nous | |
| 33 | `33-docs.md` | Documentació | |
| 34 | `34-tutorials.md` | Tutorials | |
| 35 | `35-quality.md` | Proves end-to-end, accessibilitat i CI | |
| 36 | `36-release.md` | Acceptació final i versió 1.0.0 | **Fita 3: plantilla lliurada** |

Fitxers auxiliars: `next.md` (executa la sessió següent), `continue.md` (reprèn una sessió
inacabada) i `review-fixes.md` (aplica els comentaris d'una revisió).

## Temps orientatiu

- **Per sessió:** entre 1 i 3 hores de treball de Claude Code. Algunes (05, 17, 22, 24, 25, 27, 29)
  poden necessitar `continue.md` una vegada.
- **Total:** unes 37 sessions, més les continuacions i les revisions; en calendari, entre 4 i 8
  setmanes dedicant-hi unes hores al dia. El temps de revisió és teu: llegir cada informe de
  `deploy-status/` abans d'executar la sessió següent evita haver de refer feina.

## Consells

- **Revisa a fons la sessió 19 (MVP):** si el nucli i el mòdul News estan bé, la resta de mòduls
  segueixen el mateix patró.
- Si una sessió proposa canviar una convenció pública (noms de permisos, claus del manifest, URLs)
  o afluixar la seguretat, decideix-ho tu abans de continuar.
- Si canvies alguna cosa de l'especificació, edita `docs/specification.md` al repositori nou (i, si
  vols conservar-ho, també `prompt_new.md` aquí).
- Quan la sessió 36 acabi, crea l'etiqueta `v1.0.0` del repositori nou des de GitHub (Releases).
