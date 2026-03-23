#!/bin/bash

==============================================================================

CEP DIVE CLUB - CODEBASE CONCATENATOR

Purpose: Gathers the "DNA" of the Laravel project into one evaluation file.

==============================================================================

OUTPUT_FILE="diveclub_codebase_bare.md"
PROJECT_NAME="CEP Diving Club (Laravel)"

Define the directories that hold the logic and purpose of the app

TARGET_DIRS=(
"app/Models"
"app/Http/Controllers"
"app/Http/Requests"
"app/Providers"
"database/migrations"
"routes"
)

Initialize the output file with a header

echo "# $PROJECT_NAME - Codebase Snapshot" > "$OUTPUT_FILE"
echo "Generated on: $(date)" >> "$OUTPUT_FILE"
echo "---" >> "$OUTPUT_FILE"

echo "Igniting concatenation process..."

Loop through each directory and grab .php files

for dir in "${TARGET_DIRS[@]}"; do
if [ -d "$dir" ]; then
echo "Processing: $dir"
echo -e "\n## Directory: $dir\n" >> "$OUTPUT_FILE"

    # Find all .php files in the directory
    find "$dir" -name "*.php" -type f | while read -r file; do
        echo "  > Adding: $file"
        echo "### File: $file" >> "$OUTPUT_FILE"
        echo '```php' >> "$OUTPUT_FILE"
        cat "$file" >> "$OUTPUT_FILE"
        echo -e '\n```\n' >> "$OUTPUT_FILE"
    done
else
    echo "Warning: Directory $dir not found. Skipping."
fi


done

echo "----------------------------------------------------------------"
echo "SUCCESS: Your codebase is now 'bared' in $OUTPUT_FILE"
echo "Upload this file to our conversation for a deep evaluation."
echo "----------------------------------------------------------------"
