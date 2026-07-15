# Support

This public fork stays compatible with Statistics for Strava / Dreeve while adding Garmin-compatible FIT/TCX/GPX file-import workflows.

## Product scope

Supported workflows:

- Existing Statistics for Strava / Dreeve Strava API import flow
- File-only imports from `.fit`, `.tcx`, and `.gpx` files in `storage/files/watch`
- Hybrid imports with Strava API sync plus Garmin-compatible activity exports

## Getting help

Before opening an issue, include:

- app version / commit SHA
- `IMPORT_MODE` value (`stravaApi`, `files`, or `hybrid`)
- file type involved (`fit`, `tcx`, `gpx`) if this is an import issue
- sanitized console output from the failing import/build command

## Support development

If this fork saves you time, you can support it here:

https://buymeacoffee.com/franzu
