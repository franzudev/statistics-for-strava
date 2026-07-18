# Garmin Connect automatic import

Dreeve can import activities synced to Garmin Connect through Garmin's Activity API.

## Configuration

Set these environment variables:

```env
GARMIN_CLIENT_ID=...
GARMIN_CLIENT_SECRET=...
```

Optional overrides are available because Garmin may expose evaluation/production endpoints per application approval:

```env
GARMIN_OAUTH_AUTHORIZE_URL=https://connect.garmin.com/oauth2Confirm
GARMIN_OAUTH_TOKEN_URL=https://connectapi.garmin.com/di-oauth2-service/oauth/token
GARMIN_ACTIVITY_API_BASE_URI=https://apis.garmin.com/wellness-api/rest
GARMIN_ACTIVITY_LIST_ENDPOINT=/activities
GARMIN_ACTIVITY_FILE_ENDPOINT_TEMPLATE=/activities/{activityId}/file
```

## Authorization

1. Open `/garmin-oauth` in the app.
2. Click **Connect with Garmin**.
3. Approve access in Garmin.
4. Garmin tokens are stored in the app key-value store and refreshed automatically.

## Import

Run:

```bash
bin/console app:cron:run-garmin-import --import
```

The command downloads FIT files from Garmin, parses them with the existing FIT parser, saves activities as `Garmin Connect API`, and rebuilds metrics after successful imports.
