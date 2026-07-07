# WorkTimePunchDevelop

WorkTimePunch is a small companion app for the third-party Nextcloud app
WorkTime.

The app is intentionally separate from WorkTime so local additions survive as
their own project. WorkTimePunch only operates while WorkTime is enabled. If
WorkTime is disabled, WorkTimePunch disables itself automatically.

## Test environment

The first test target is NC-HVML.

## App IDs

- Nextcloud app ID: `worktimepunch`
- Required app: `worktime`
- Development repository: `WorkTimePunchDevelop`
- Release repository: `WorkTimePunch`

## Current scope

- register a dependency guard for WorkTime
- disable WorkTimePunch when WorkTime is disabled
- load a small top-bar button on logged-in Nextcloud pages
- keep the first button conservative: it opens the WorkTime app

Actual punch actions will be added after the WorkTime routes/API are mapped.

