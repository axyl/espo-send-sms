# SMS Message EspoCRM Extension

Adds the ability to Send SMS from the Activities sub panel of Person and Company views in EspoCRM.

Requires the SMS Providers Extension to be installed first - and configured.

Adds an SMS entity with a history of the messages sent.

There's no concept of replies.

## Build

The extension can be packaged as a ZIP with:

```bash
./build-extension.sh
```

The script creates `build/sms-message-<version>.zip`.

Note: the build script increments `manifest.json` when building!

## Install

1. Build the ZIP archive.
2. In EspoCRM, go to `Administration > Extensions`.
3. Upload the ZIP package.

## Notes

- The extension uses a custom `/Activities/...` API layer for SMS activity/history support.
- That path was kept intentionally because prior attempts to fold SMS fully into the core Activities query path caused SQL UNION/cardinality failures.
- If you have custom entities that you want to use with this, then your clientDefs JSON file needs to set the view for the activities and history to the custom version of the view.

Snippet Example:

```
"sidePanels": {
        "detail": [
            {
                "name": "activities",
                "label": "Activities",
                "view": "custom:views/record/panels/activities",
                "aclScope": "Activities"
            },
            {
                "name": "history",
                "label": "History",
                "view": "custom:views/record/panels/history",
                "aclScope": "Activities"
            } ...
```

## License

This repository is published under the GNU Affero General Public License v3.0 or later. See `LICENSE.txt`.
