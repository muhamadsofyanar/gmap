#!/bin/sh
set -eu
php tests/team.php
php tests/install.php
php tests/inbox.php
php tests/conversations.php
php tests/pipeline_broadcast.php
php tests/import_file.php
php tests/import_xlsx.php
php tests/import_flow.php
node tests/theme.test.cjs
python3 tests/test_inbox_http.py
python3 tests/test_webhook.py
if [ "${ALLOW_TEST_RESET:-}" = 1 ]; then
    python3 tests/test_http.py
    php tests/legacy.php
fi
