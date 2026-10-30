#!/bin/sh
set -eu
php tests/team.php
php tests/install.php
if [ "${ALLOW_TEST_RESET:-}" = 1 ]; then
    python3 tests/test_http.py
    php tests/legacy.php
fi
