<?php

return [
    'offsite_host' => env('BACKUP_OFFSITE_HOST'),
    'offsite_user' => env('BACKUP_OFFSITE_USER', 'dcms-backup'),
    'offsite_key' => env('BACKUP_OFFSITE_KEY', '/home/clubcep/.ssh/backup_key'),
    'offsite_dir' => env('BACKUP_OFFSITE_DIR', 'backups'),
];
