#!/bin/bash
set -e

php artisan queue:work
