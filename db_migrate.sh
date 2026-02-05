#!/bin/bash

# POA Savings Management System - Migration Helper Script

echo "--- Database Migration Helper ---"

# 1. Take a snapshot for safety
SNAPSHOT_NAME="pre_migrate_$(date +%Y%m%d_%H%M%S)"
echo "Taking a database snapshot: $SNAPSHOT_NAME..."
ddev snapshot --name "$SNAPSHOT_NAME"

if [ $? -eq 0 ]; then
    echo "Snapshot successful."
else
    echo "Snapshot failed! Aborting migration for safety."
    exit 1
fi

# 2. Run Phinx migrations
echo "Running migrations..."
ddev exec vendor/bin/phinx migrate

if [ $? -eq 0 ]; then
    echo "Migrations applied successfully!"
    echo "You can restore the previous state if needed using: ddev snapshot restore $SNAPSHOT_NAME"
else
    echo "Migrations failed! You might want to check the logs or restore the snapshot: ddev snapshot restore $SNAPSHOT_NAME"
    exit 1
fi
