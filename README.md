# Script to check a CSV of sites regularly

## Setup

Generate and save credentials.json from: https://developers.google.com/drive/api/v3/quickstart/php

```
composer install
```

Set the Google spreadsheet Id: (https://docs.google.com/spreadsheets/d/SPREADSHEETID/edit#gid=0)
```
echo -n "SPREADSHEETID" > .spreadsheetId
```


## Run with

```
php checker.php
```
