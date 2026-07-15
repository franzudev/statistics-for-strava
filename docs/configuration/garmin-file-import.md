# Garmin-compatible file import

This public fork keeps the existing Statistics for Strava / Dreeve data model and build output, while adding a Garmin-friendly fallback path based on exported activity files.

## Supported files

Copy Garmin Connect exports into the watch folder:

```text
./storage/files/watch
```

Supported extensions:

- `.fit`
- `.tcx`
- `.gpx`

The daemon or manual import command parses the files, stores the activities, imports streams and laps when present, calculates metrics, and rebuilds the static dashboard.

## Import modes

Set `IMPORT_MODE` in `.env`:

```bash
# Strava API only; default SFS-compatible behavior
IMPORT_MODE=stravaApi

# File import only
IMPORT_MODE=files

# Strava API plus Garmin-compatible file import
IMPORT_MODE=hybrid
```

Use `hybrid` when you want to keep Strava sync active and also import Garmin exports from the watch folder. Use `files` when you want to avoid Strava API dependency entirely.

## Manual import

```bash
docker compose exec app bin/console app:data:import
docker compose exec app bin/console app:data:build
```

For new deployments, prefer `hybrid` over replacing Strava imports outright: it preserves compatibility with existing Statistics for Strava data while adding Garmin continuity.

## Product/support note

This fork is intended to be published as a public GitHub product fork. It should remain compatible with upstream Statistics for Strava / Dreeve concepts, routes, storage layout, and generated dashboard output while making Garmin activity export import a first-class supported workflow.

Support this fork: https://buymeacoffee.com/franzu
